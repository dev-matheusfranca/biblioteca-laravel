<?php

namespace App\Http\Controllers;

use App\Models\PortalNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalNoticeController extends Controller
{
    public function index(Request $request)
    {
        $avisos = PortalNotice::query()
            ->where('usuario_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('portal.avisos.index', compact('avisos'));
    }

    public function read(Request $request, PortalNotice $aviso)
    {
        DB::transaction(function () use ($request, $aviso) {
            $locked = PortalNotice::query()->lockForUpdate()->findOrFail($aviso->id);
            abort_unless($locked->usuario_id === $request->user()->id, 404);
            if ($locked->read_at === null) {
                $locked->forceFill(['read_at' => now()])->save();
            }
        }, 3);

        return back()->with('success', 'Aviso marcado como lido.');
    }
}
