<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TableExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TableExportController extends Controller
{
    /**
     * PDF of a payment/billing table with the filters the page had active. Needs `documents.generate`
     * (route) plus the view permission of the module the table belongs to.
     */
    public function show(Request $request, TableExportService $service, string $dataset)
    {
        $permission = $service->permissionFor($dataset);

        abort_if($permission === null, 404, 'Tabel ini tidak tersedia untuk dicetak.');
        abort_unless($request->user()->can($permission), 403, 'Anda tidak memiliki akses ke data tabel ini.');

        $table = $service->build($dataset, $request);

        return Pdf::loadHTML(view('pdf.table-report', $table)->render())
            ->setPaper('a4', 'landscape')
            ->download($table['filename']);
    }
}
