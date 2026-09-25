<?php

namespace App\Http\Controllers;

use App\Actions\Circulation\UpdateCirculationPolicy;
use App\Http\Requests\Circulation\UpdateCirculationPolicyRequest;
use App\Models\CirculationPolicy;
use App\Services\CurrentCirculationPolicy;
use DomainException;

class CirculationPolicyController extends Controller
{
    public function edit(CurrentCirculationPolicy $policies)
    {
        $policy = $policies->get();
        $history = CirculationPolicy::query()->orderByDesc('version')->get();

        return view('configuracoes.circulacao', compact('policy', 'history'));
    }

    public function update(UpdateCirculationPolicyRequest $request, UpdateCirculationPolicy $update)
    {
        $data = $request->validated();
        foreach (['loan_days', 'max_open_loans', 'max_renewals', 'renewal_days', 'pickup_hours', 'expected_version'] as $field) {
            $data[$field] = $request->integer($field);
        }
        $data['blocks_overdue'] = $request->boolean('blocks_overdue');
        try {
            $update->execute($request->user(), $data);
        } catch (DomainException $exception) {
            return back()->withErrors(['expected_version' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('configuracoes.circulacao.edit')->with('success', 'Nova versão da política de circulação registrada.');
    }
}
