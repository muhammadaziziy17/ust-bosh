<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class OwnerReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_report_has_all_sheets_and_full_sales_data(): void
    {
        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $otherShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);
        $otherCashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $otherShop->id]);

        // Yakunlangan sotuv
        Sale::factory()->for($shop)->create([
            'cashier_id' => $cashier->id,
            'sale_no' => 'S-COMPLETED1',
            'status' => Sale::STATUS_COMPLETED,
            'payment_method' => Sale::PAYMENT_CASH,
            'sold_at' => now(),
            'total' => 300000,
        ]);

        // Bekor qilingan sotuv: xulosaga kirmaydi, lekin ro'yxatda ko'rinadi
        Sale::factory()->for($shop)->create([
            'cashier_id' => $cashier->id,
            'sale_no' => 'S-VOIDED001',
            'status' => Sale::STATUS_VOIDED,
            'voided_at' => now(),
            'void_reason' => 'Xato razmer',
            'payment_method' => Sale::PAYMENT_CARD,
            'sold_at' => now(),
            'total' => 777777,
        ]);

        // Boshqa do'kon sotuvi eksportga umuman tushmasligi kerak
        Sale::factory()->for($otherShop)->create([
            'cashier_id' => $otherCashier->id,
            'sale_no' => 'S-OTHER0001',
            'status' => Sale::STATUS_COMPLETED,
            'sold_at' => now(),
            'total' => 999999,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('owner.reports.export', ['range' => 'today']));

        $response->assertOk();
        $response->assertDownload('hisobot_2026-09-20_2026-09-20.xlsx');

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());

        foreach ([
            __('owner.export_sheet_summary'),
            __('owner.export_sheet_sales'),
            __('owner.export_sheet_daily'),
            __('owner.export_sheet_products'),
            __('owner.export_sheet_payments'),
            __('owner.export_sheet_stock'),
        ] as $title) {
            $this->assertNotNull($spreadsheet->getSheetByName($title), "Varaq topilmadi: {$title}");
        }

        // Xulosa: faqat yakunlangan sotuvlar hisobga olinadi
        $summary = $this->sheetByTitle($spreadsheet, __('owner.export_sheet_summary'));
        $this->assertSame(300000.0, (float) $this->valueFor($summary, __('owner.report_revenue')));
        $this->assertSame(300000.0, (float) $this->valueFor($summary, __('owner.payment_cash')));
        $this->assertSame(0.0, (float) $this->valueFor($summary, __('owner.payment_card')));

        // Sotuvlar varag'i: yakunlangan va bekor qilingan ikkalasi ham bor
        $salesSheet = $this->sheetByTitle($spreadsheet, __('owner.export_sheet_sales'));
        $this->assertTrue($this->sheetContains($salesSheet, 'S-COMPLETED1'));
        $this->assertTrue($this->sheetContains($salesSheet, 'S-VOIDED001'));
        $this->assertTrue($this->sheetContains($salesSheet, 'Xato razmer'));
        $this->assertTrue($this->sheetContains($salesSheet, __('owner.sale_status_voided')));

        // Boshqa do'kon ma'lumoti hech bir varaqda yo'q
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $this->assertFalse(
                $this->sheetContains($sheet, 'S-OTHER0001'),
                'Boshqa do\'kon sotuvi eksportda chiqdi: '.$sheet->getTitle()
            );
        }
    }

    public function test_excel_export_writes_user_text_as_string_not_formula(): void
    {
        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE, 'name' => '=BAD()']);
        $owner = $this->makeOwner($shop);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        Sale::factory()->for($shop)->create([
            'cashier_id' => $cashier->id,
            'sale_no' => 'S-FORMULA01',
            'status' => Sale::STATUS_COMPLETED,
            'payment_method' => Sale::PAYMENT_CASH,
            'sold_at' => now(),
            'total' => 100000,
        ]);

        Sale::factory()->for($shop)->create([
            'cashier_id' => $cashier->id,
            'sale_no' => 'S-FORMULA02',
            'status' => Sale::STATUS_VOIDED,
            'voided_at' => now(),
            'void_reason' => '=HYPERLINK("http://evil.example")',
            'payment_method' => Sale::PAYMENT_CASH,
            'sold_at' => now(),
            'total' => 50000,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('owner.reports.export', ['range' => 'today']));
        $response->assertOk();

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());

        $summary = $this->sheetByTitle($spreadsheet, __('owner.export_sheet_summary'));
        $shopNameCell = $summary->getCell('A1');
        $this->assertSame('=BAD()', $shopNameCell->getValue());
        $this->assertSame(DataType::TYPE_STRING, $shopNameCell->getDataType());

        $salesSheet = $this->sheetByTitle($spreadsheet, __('owner.export_sheet_sales'));
        $voidReasonCell = null;

        foreach ($salesSheet->getRowIterator(2) as $row) {
            if ($salesSheet->getCell('B'.$row->getRowIndex())->getValue() === 'S-FORMULA02') {
                $voidReasonCell = $salesSheet->getCell('H'.$row->getRowIndex());
            }
        }

        $this->assertNotNull($voidReasonCell);
        $this->assertSame('=HYPERLINK("http://evil.example")', $voidReasonCell->getValue());
        $this->assertSame(DataType::TYPE_STRING, $voidReasonCell->getDataType());
    }

    public function test_export_limits_sales_sheet_and_keeps_totals_complete(): void
    {
        config(['reports.max_export_sales' => 2]);

        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        foreach (['S-OLD00001' => 100000, 'S-NEW00002' => 200000, 'S-NEW00003' => 300000] as $saleNo => $total) {
            Sale::factory()->for($shop)->create([
                'cashier_id' => $cashier->id,
                'sale_no' => $saleNo,
                'status' => Sale::STATUS_COMPLETED,
                'payment_method' => Sale::PAYMENT_CASH,
                'sold_at' => now(),
                'total' => $total,
            ]);
        }

        $this->actingAs($owner);

        $response = $this->get(route('owner.reports.export', ['range' => 'today']));
        $response->assertOk();

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());

        $salesSheet = $this->sheetByTitle($spreadsheet, __('owner.export_sheet_sales'));
        $this->assertFalse($this->sheetContains($salesSheet, 'S-OLD00001'));
        $this->assertTrue($this->sheetContains($salesSheet, 'S-NEW00002'));
        $this->assertTrue($this->sheetContains($salesSheet, 'S-NEW00003'));
        $this->assertTrue($this->sheetContains(
            $salesSheet,
            __('owner.export_sales_truncated', ['shown' => 2, 'total' => 3])
        ));

        // Xulosa ko'rsatkichlari to'liq davr bo'yicha qoladi
        $summary = $this->sheetByTitle($spreadsheet, __('owner.export_sheet_summary'));
        $this->assertSame(600000.0, (float) $this->valueFor($summary, __('owner.report_revenue')));
    }

    public function test_export_limits_products_and_low_stock_sheets(): void
    {
        config([
            'reports.max_export_products' => 1,
            'reports.max_export_low_stock' => 1,
        ]);

        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        $productA = Product::factory()->for($shop)->create(['name' => "Ko'ylak", 'sale_price' => 100000]);
        $variantA = ProductVariant::factory()->for($shop)->for($productA)->create([
            'size' => 'M',
            'stock_on_hand' => 1,
            'reorder_level' => 5,
        ]);

        $productB = Product::factory()->for($shop)->create(['name' => 'Shim', 'sale_price' => 50000]);
        $variantB = ProductVariant::factory()->for($shop)->for($productB)->create([
            'size' => 'L',
            'stock_on_hand' => 1,
            'reorder_level' => 5,
        ]);
        ProductVariant::factory()->for($shop)->for($productB)->create([
            'size' => 'XL',
            'stock_on_hand' => 0,
            'reorder_level' => 5,
        ]);

        $sale = Sale::factory()->for($shop)->create([
            'cashier_id' => $cashier->id,
            'status' => Sale::STATUS_COMPLETED,
            'payment_method' => Sale::PAYMENT_CASH,
            'sold_at' => now(),
            'total' => 250000,
        ]);

        SaleItem::factory()->for($sale)->create([
            'shop_id' => $shop->id,
            'product_variant_id' => $variantA->id,
            'product_name_snapshot' => "Ko'ylak",
            'quantity' => 2,
            'line_total' => 200000,
        ]);
        SaleItem::factory()->for($sale)->create([
            'shop_id' => $shop->id,
            'product_variant_id' => $variantB->id,
            'product_name_snapshot' => 'Shim',
            'quantity' => 1,
            'line_total' => 50000,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('owner.reports.export', ['range' => 'today']));
        $response->assertOk();

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());

        $productsSheet = $this->sheetByTitle($spreadsheet, __('owner.export_sheet_products'));
        $this->assertTrue($this->sheetContains($productsSheet, "Ko'ylak"));
        $this->assertFalse($this->sheetContains($productsSheet, 'Shim'));
        $this->assertTrue($this->sheetContains(
            $productsSheet,
            __('owner.export_products_truncated', ['shown' => 1, 'total' => 2])
        ));

        $stockSheet = $this->sheetByTitle($spreadsheet, __('owner.export_sheet_stock'));
        $this->assertTrue($this->sheetContains(
            $stockSheet,
            __('owner.export_low_stock_truncated', ['shown' => 1, 'total' => 2])
        ));

        $this->assertTrue($this->sheetContains($productsSheet, __('owner.export_total_shown_products')));

        // Xulosa butun davr bo'yicha to'liq sonni ko'rsatadi (limitdan oldingi)
        $summary = $this->sheetByTitle($spreadsheet, __('owner.export_sheet_summary'));
        $this->assertSame(2, (int) $this->valueFor($summary, __('owner.stat_low_stock')));
    }

    public function test_reports_page_warns_when_export_is_truncated(): void
    {
        config(['reports.max_export_sales' => 1]);

        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        Sale::factory()->count(2)->for($shop)->create([
            'cashier_id' => $cashier->id,
            'status' => Sale::STATUS_COMPLETED,
            'payment_method' => Sale::PAYMENT_CASH,
            'sold_at' => now(),
            'total' => 100000,
        ]);

        $this->actingAs($owner);

        $this->get(route('owner.reports.index', ['range' => 'today']))
            ->assertOk()
            ->assertSee(__('owner.export_truncated_hint', ['limit' => 1]));
    }

    public function test_owner_can_open_printable_report(): void
    {
        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        $shop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $owner = $this->makeOwner($shop);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'shop_id' => $shop->id]);

        Sale::factory()->for($shop)->create([
            'cashier_id' => $cashier->id,
            'status' => Sale::STATUS_COMPLETED,
            'payment_method' => Sale::PAYMENT_CASH,
            'sold_at' => now(),
            'total' => 300000,
        ]);

        $this->actingAs($owner);

        $this->get(route('owner.reports.print', ['range' => 'today']))
            ->assertOk()
            ->assertSee($shop->name)
            ->assertSee(format_som(300000), false);
    }

    private function makeOwner(Shop $shop): User
    {
        return User::factory()->create([
            'role' => User::ROLE_OWNER,
            'shop_id' => $shop->id,
        ]);
    }

    private function sheetByTitle(Spreadsheet $spreadsheet, string $title): Worksheet
    {
        $sheet = $spreadsheet->getSheetByName($title);
        $this->assertNotNull($sheet, "Varaq topilmadi: {$title}");

        return $sheet;
    }

    private function valueFor(Worksheet $sheet, string $label): mixed
    {
        foreach ($sheet->getRowIterator() as $row) {
            $rowNumber = $row->getRowIndex();

            if ($sheet->getCell('A'.$rowNumber)->getValue() === $label) {
                return $sheet->getCell('B'.$rowNumber)->getValue();
            }
        }

        return null;
    }

    private function sheetContains(Worksheet $sheet, string $needle): bool
    {
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                if ((string) $cell->getValue() === $needle) {
                    return true;
                }
            }
        }

        return false;
    }
}
