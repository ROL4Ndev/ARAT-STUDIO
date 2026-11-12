<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\AccessRequest;
use App\Services\ClientAccessService;
use Illuminate\Http\Request;

class AccessController extends Controller
{
    public function create()
    {
        return view('client.access');
    }

    public function request(AccessRequest $request, ClientAccessService $access)
    {
        $access->request($request->validated('code'), $request->validated('email'));

        return back()->with('status', 'Si los datos coinciden, se preparó un mensaje con acceso temporal. En desarrollo el transporte log no entrega correos reales.');
    }

    public function form(string $token)
    {
        return response()->view('client.verify', compact('token'))->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function consume(Request $request, string $token, ClientAccessService $access)
    {
        $access->consume($request, $token);

        return redirect()->route('client.dossier');
    }

    public function revoke(Request $request, ClientAccessService $access)
    {
        $access->revoke($request);

        return redirect()->route('client.access');
    }
}
