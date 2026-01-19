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
     * Checklist bisa diubah (tambah/edit/hapus) selama indikator masih Ditolak.
     * - Jika item punya id => update
     * - Jika id kosong => insert baru
     * - Jika delete=1 dan id ada => soft delete (active=0)
     */
    public function bulkUpsert(Request $request, $detailId)
    {
        $data = $this->validateBulk($request);

        $detail = SelfEvaluationDetail::with(['status', 'auditChecklists'])
            ->findOrFail($detailId);

        // Hanya saat Ditolak (sesuai alur audit)
        if (($detail->status->name ?? null) !== 'Ditolak') {
            return back()->with('error', 'Daftar tilik hanya bisa diubah saat indikator Ditolak.');
        }

        $userRole = Auth::user()->userRole ?? null;

        DB::transaction(function () use ($data, $detail, $userRole) {

            // Existing aktif by id
            $existing = $detail->auditChecklists()
                ->where('active', 1)
                ->get()
                ->keyBy('id');

            // Max number untuk id ACLxxx
            $maxNum = (int) AuditChecklist::where('id', 'like', 'ACL%')
                ->selectRaw("MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as maxnum")
                ->value('maxnum');

            foreach ($data['items'] as $x) {
                $id       = $x['id'] ?? null;
                $itemText = trim((string)($x['item'] ?? ''));
                $noteText = isset($x['note']) ? trim((string)$x['note']) : null;
                $toDelete = !empty($x['delete']);

                // DELETE => soft delete
                if ($toDelete && $id) {
                    if ($existing->has($id)) {
                        $existing[$id]->update([
                            'active' => 0,
                            'updated_by' => $userRole?->id,
                        ]);
                    }
                    continue;
                }

                // UPDATE
                if ($id && $existing->has($id)) {
                    $existing[$id]->update([
                        'item' => $itemText,
                        'note' => $noteText ?: null,
                        'updated_by' => $userRole?->id,
                        'active' => 1,
                    ]);
                    continue;
                }

                // INSERT baru
                $maxNum++;
                AuditChecklist::create([
                    'id' => 'ACL' . str_pad((string)$maxNum, 3, '0', STR_PAD_LEFT),
                    'self_evaluation_detail_id' => $detail->id,
                    'item' => $itemText,
                    'note' => $noteText ?: null,
                    'created_by' => $userRole?->id,
                    'updated_by' => $userRole?->id,
                    'active' => 1,
                ]);
            }
        });

        return back()->with('success', 'Daftar tilik berhasil disimpan.');
    }

    private function validateBulk(Request $request): array
    {
        return $request->validate([
            'items' => 'required|array|min:1|max:80',
            'items.*.id' => 'nullable|string|max:64',
            'items.*.item' => 'required|string|max:255',
            'items.*.note' => 'nullable|string|max:1000',
            'items.*.delete' => 'nullable|boolean',
        ]);
    }
}
