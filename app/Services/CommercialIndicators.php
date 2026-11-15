<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CommercialIndicators
{
    public const STATES = [
        'pendiente' => 'Pendiente', 'evaluacion' => 'En evaluación', 'observada' => 'Observada',
        'aprobado' => 'Aprobada', 'rechazado' => 'Rechazada', 'cancelado' => 'Cancelada', 'cerrada' => 'Cerrada',
    ];

    public function period(array $filters = []): array
    {
        $today = CarbonImmutable::today('America/Lima');
        $from = isset($filters['from']) ? CarbonImmutable::parse($filters['from'], 'America/Lima')->startOfDay() : null;
        $to = isset($filters['to'])
            ? CarbonImmutable::parse($filters['to'], 'America/Lima')->endOfDay()
            : $today->max($from ?? $today)->endOfDay();
        $from ??= $to->subDays(29)->startOfDay();

        return compact('from', 'to');
    }

    public function overview(User $user, array $period, ?Builder $filteredRequests = null): array
    {
        $visible = $filteredRequests ? clone $filteredRequests : ServiceRequest::visibleTo($user);
        $inPeriod = (clone $visible)->whereBetween('created_at', [$period['from'], $period['to']]);
        $counts = (clone $inPeriod)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $states = [];
        foreach (self::STATES as $key => $label) {
            $states[] = ['key' => $key, 'label' => $label, 'count' => (int) ($counts[$key] ?? 0)];
        }
        $attention = (clone $visible)->whereIn('status', ['pendiente', 'evaluacion', 'observada']);

        return [
            'period' => $period,
            'filtered' => $filteredRequests !== null,
            'weeks' => $this->weeklyCounts($visible, $period),
            'states' => $states,
            'received' => (int) $counts->sum(),
            'attentionCount' => (clone $attention)->count(),
            'attentionRequests' => (clone $attention)->oldest('created_at')->limit(5)->get(['id', 'code', 'service', 'status', 'created_at']),
            'recentRequests' => (clone $visible)->latest('id')->limit(6)->get(['id', 'code', 'service', 'status', 'created_at']),
        ];
    }

    public function commercialMetrics(User $user, array $period): array
    {
        $visible = ServiceRequest::visibleTo($user);
        $counts = (clone $visible)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $fromUtc = $period['from']->utc();
        $toUtc = $period['to']->utc();
        $cohort = (clone $visible)->whereRaw('(SELECT MIN(issued_at) FROM quotations WHERE quotations.service_request_id = service_requests.id) BETWEEN ? AND ?', [
            $fromUtc->format('Y-m-d H:i:s.u'), $toUtc->format('Y-m-d H:i:s.u'),
        ])->selectRaw("COUNT(*) AS total, SUM(CASE WHEN closure_outcome = 'aceptada' THEN 1 ELSE 0 END) AS accepted")->first();
        $issued = Quotation::whereIn('service_request_id', (clone $visible)->select('id'))
            ->whereBetween('issued_at', [$fromUtc, $toUtc])->count();

        return [
            'counts' => $counts, 'denominator' => (int) $cohort->total,
            'accepted' => (int) $cohort->accepted, 'issued' => $issued,
            'minutes' => $this->averageEvaluation($visible, $period),
        ];
    }

    private function averageEvaluation(Builder $visible, array $period): ?float
    {
        // created_at es hora local histórica; first_evaluated_at se conserva en UTC.
        $difference = DB::getDriverName() === 'sqlite'
            ? '(julianday(first_evaluated_at) - julianday(created_at)) * 1440.0'
            : 'TIMESTAMPDIFF(MICROSECOND, created_at, first_evaluated_at) / 60000000.0';
        $offsetMinutes = $period['from']->getOffset() / 60;
        $result = (clone $visible)->whereBetween('created_at', [$period['from'], $period['to']])
            ->whereNotNull('first_evaluated_at')
            ->selectRaw('AVG('.$difference.' + ?) AS minutes', [$offsetMinutes])->first();

        // Unificar el ruido de coma flotante de julianday con TIMESTAMPDIFF.
        return $result->minutes === null ? null : round((float) $result->minutes, 6);
    }

    private function weeklyCounts(Builder $visible, array $period): array
    {
        // Como máximo 52 intervalos; ningún expediente se hidrata para las agregaciones.
        // Carbon adapta el inicio de semana a la configuración regional; aquí es lunes explícito.
        $firstWeek = $period['from']->startOfWeek(CarbonInterface::MONDAY)
            ->max($period['to']->startOfWeek(CarbonInterface::MONDAY)->subWeeks(51));
        $weeks = [];
        $selects = [];
        $bindings = [];
        for ($week = $firstWeek; $week->lessThanOrEqualTo($period['to']); $week = $week->addWeek()) {
            $from = $week->max($period['from']);
            $to = $week->endOfWeek(CarbonInterface::SUNDAY)->min($period['to']);
            $index = count($weeks);
            $weeks[] = ['label' => $from->format('d/m').'–'.$to->format('d/m'), 'from' => $from->toDateString(), 'to' => $to->toDateString()];
            $selects[] = 'SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS week_'.$index;
            $bindings[] = $from->format('Y-m-d H:i:s');
            $bindings[] = $to->format('Y-m-d H:i:s');
        }
        $row = (clone $visible)->whereBetween('created_at', [$period['from'], $period['to']])
            ->selectRaw(implode(', ', $selects), $bindings)->first();
        foreach ($weeks as $index => &$week) {
            $week['count'] = (int) $row->{'week_'.$index};
        }

        return $weeks;
    }
}
