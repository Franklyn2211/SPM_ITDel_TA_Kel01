<?php

namespace App\Http\Controllers\Auditor;

use App\Http\Controllers\Controller;
use App\Models\AuditChecklist;
use App\Models\SelfEvaluationDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditChecklistController extends Controller
{
    /**
     * ONCE ONLY: checklist cuma bisa dibuat sekali dan tidak bisa diubah.
     */
    public function bulkStoreOnce(Request $request, $detailId)
    {
        $this->validateBulk($request);

        $detail = SelfEvaluationDetail::with(['status', 'auditChecklists'])
            ->findOrFail($detailId);

        // Batasi hanya saat Ditolak (biar sesuai alur audit)
        if (($detail->status->name ?? null) !== 'Ditolak') {
            return back()->with('error', 'Daftar tilik hanya bisa dibuat saat indikator Ditolak.');
        }

        // Sudah ada => stop. Tidak bisa edit / replace.
        if ($detail->auditChecklists()->exists()) {
            return back()->with('error', 'Daftar tilik sudah dibuat dan tidak bisa diubah.');
        }

        $userRole = Auth::user()->userRole ?? null;

        DB::transaction(function () use ($request, $detail, $userRole) {
            // ambil max nomor sekali
            $maxNum = (int) AuditChecklist::where('id', 'like', 'ACL%')
                ->selectRaw("MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as maxnum")
                ->value('maxnum');

            $now = now();
            $payload = [];

            foreach ($request->items as $x) {
                $item = trim($x['item'] ?? '');
                if ($item === '') continue;

                $maxNum++;
                $payload[] = [
                    'id' => 'ACL' . str_pad((string) $maxNum, 3, '0', STR_PAD_LEFT),
                    'self_evaluation_detail_id' => $detail->id,
                    'item' => $item,
                    'note' => !empty($x['note']) ? trim($x['note']) : null,
                    'created_by' => $userRole?->id,
                    'updated_by' => $userRole?->id,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($payload)) {
                DB::table('audit_checklists')->insert($payload);
            }
        });

        return back()->with('success', 'Daftar tilik berhasil disimpan (sekali saja).');
    }

    private function validateBulk(Request $request): void
    {
        $request->validate([
            'items' => 'required|array|min:1|max:80',
            'items.*.item' => 'required|string|max:255',
            'items.*.note' => 'nullable|string|max:1000',
        ]);
    }
}
