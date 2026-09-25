<?php

namespace App\Http\Controllers;

use App\Services\Operations\OperationsHealth;
use Illuminate\Http\Request;

class OperationsHealthController extends Controller
{
    public function __invoke(Request $request, OperationsHealth $health)
    {
        $report = $health->report();
        $status = $report['healthy'] ? 200 : 503;

        if ($request->expectsJson()) {
            return response()
                ->json(['status' => $report['healthy'] ? 'healthy' : 'degraded'] + $report, $status)
                ->header('Cache-Control', 'no-store');
        }

        return response()
            ->view('operacao.saude', compact('report'), $status)
            ->header('Cache-Control', 'no-store');
    }
}
