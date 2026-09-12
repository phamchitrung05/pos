<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pos\CreateReceiptTestPrintJob;
use App\Enums\PrinterPaperWidth;
use App\Enums\PrinterType;
use App\Http\Controllers\Controller;
use App\Models\Printer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Quản lý máy in hóa đơn trong POS theo tenant của user hiện tại. */
final class PosPrinterController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        [$user, $store] = $this->context($request);
        Gate::forUser($user)->authorize('create', Printer::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip_address' => ['required', 'ip'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'paper_width_mm' => ['required', Rule::enum(PrinterPaperWidth::class)],
        ]);

        $printer = new Printer;
        $printer->forceFill([
            'store_id' => $store->getKey(),
            'name' => $validated['name'],
            'printer_type' => PrinterType::Receipt,
            'ip_address' => $validated['ip_address'],
            'port' => (int) $validated['port'],
            'paper_width_mm' => PrinterPaperWidth::from((int) $validated['paper_width_mm']),
            'is_active' => true,
        ])->save();

        return response()->json(['data' => $this->printerResult($printer)], 201);
    }

    public function testPrint(Request $request, int $printer, CreateReceiptTestPrintJob $createTestPrintJob): JsonResponse
    {
        [$user, $store] = $this->context($request);
        $printerModel = Printer::query()->where('store_id', $store->getKey())->findOrFail($printer);
        $job = $createTestPrintJob->handle($store, $printerModel, $user);

        return response()->json([
            'data' => [
                'id' => (int) $job->getKey(),
                'printer_id' => (int) $job->printer_id,
                'print_type' => $job->print_type->value,
                'status' => $job->status->value,
            ],
        ], 202);
    }

    /** @return array<string, mixed> */
    private function printerResult(Printer $printer): array
    {
        return [
            'id' => (int) $printer->getKey(),
            'name' => $printer->name,
            'type' => $printer->printer_type->value,
            'ip_address' => $printer->ip_address,
            'port' => $printer->port,
            'paper_width_mm' => $printer->paper_width_mm->value,
            'dots_per_line' => $printer->paper_width_mm->dotsPerLine(),
        ];
    }

    /** @return array{User, Store} */
    private function context(Request $request): array
    {
        $user = $request->user();
        $store = $request->attributes->get('pos_store');
        abort_unless($user instanceof User && $store instanceof Store, 403);

        return [$user, $store];
    }
}
