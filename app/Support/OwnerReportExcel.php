<?php

namespace App\Support;

use App\Models\Sale;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Do'kon egasi hisobotini chiroyli, ko'p varaqli Excel kitobi ko'rinishida yasaydi.
 *
 * Kutilgan ma'lumotlar OwnerDashboardController::reportExportData() dan keladi:
 * shop, from, to, generatedAt, sales, stats, paymentSplit, dailySales,
 * topProducts, lowStockVariants.
 */
class OwnerReportExcel
{
    private const COLOR_INK = '1F2A24';

    private const COLOR_SOFT = '5A6660';

    private const COLOR_ACCENT = 'C96F2E';

    private const COLOR_LINE = 'DCD2BE';

    private const COLOR_PAPER = 'F6F1E7';

    private const COLOR_WHITE = 'FFFFFF';

    private const MONEY_FORMAT = '#,##0" so‘m"';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private readonly array $data) {}

    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $properties = $spreadsheet->getProperties();
        $properties->setCreator('UstBosh');
        $properties->setTitle(__('owner.reports_title'));
        $properties->setDescription(
            __('owner.report_period').': '.$this->periodLabel()
        );

        $this->buildSummarySheet($spreadsheet->getActiveSheet());
        $this->buildSalesSheet($spreadsheet->createSheet());
        $this->buildDailySheet($spreadsheet->createSheet());
        $this->buildProductsSheet($spreadsheet->createSheet());
        $this->buildPaymentsSheet($spreadsheet->createSheet());
        $this->buildStockSheet($spreadsheet->createSheet());

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function buildSummarySheet(Worksheet $sheet): void
    {
        $sheet->setTitle(__('owner.export_sheet_summary'));
        $sheet->setShowGridlines(false);

        // Sarlavha bloki
        $sheet->mergeCells('A1:C1');
        $this->writeText($sheet, 'A1', $this->data['shop']?->name ?? __('landing.brand'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::COLOR_INK);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->mergeCells('A2:C2');
        $sheet->setCellValue('A2', __('owner.reports_title'));
        $sheet->getStyle('A2')->getFont()->setSize(11)->getColor()->setRGB(self::COLOR_ACCENT);

        $sheet->mergeCells('A3:C3');
        $sheet->setCellValue('A3', __('owner.report_period').': '.$this->periodLabel());
        $sheet->getStyle('A3')->getFont()->setSize(10)->getColor()->setRGB(self::COLOR_SOFT);

        $sheet->mergeCells('A4:C4');
        $sheet->setCellValue('A4', __('owner.export_generated_at').': '.$this->data['generatedAt']->format('d.m.Y H:i'));
        $sheet->getStyle('A4')->getFont()->setSize(10)->getColor()->setRGB(self::COLOR_SOFT);

        // Asosiy ko'rsatkichlar
        $row = 6;
        $this->writeSectionTitle($sheet, $row, __('owner.export_kpi_title'), 3);
        $row++;

        $stats = $this->data['stats'];
        $kpiRows = [
            [__('owner.report_revenue'), $stats['revenue'], true],
            [__('owner.report_sales_count'), $stats['sales'], false],
            [__('owner.report_avg_check'), round($stats['average_check']), true],
            [__('owner.report_items_sold'), $stats['items'], false],
            [__('owner.stat_low_stock'), $stats['low_stock'], false],
        ];

        $isAlternate = false;
        foreach ($kpiRows as [$label, $value, $isMoney]) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $value);

            $this->styleRow($sheet, $row, 2, $isAlternate);
            $this->applyMoneyFormat($sheet, 'B'.$row, $isMoney);

            $isAlternate = ! $isAlternate;
            $row++;
        }

        // To'lov turlari
        $row++;
        $this->writeSectionTitle($sheet, $row, __('owner.report_payments_title'), 3);
        $row++;
        $this->writeTableHeader($sheet, $row, [
            __('owner.table_payment'),
            __('owner.table_total'),
            __('owner.export_share'),
        ]);
        $row++;

        $paymentSplit = $this->data['paymentSplit'];
        foreach ([
            [__('owner.payment_cash'), $paymentSplit['cash'], $paymentSplit['cash_percent']],
            [__('owner.payment_card'), $paymentSplit['card'], $paymentSplit['card_percent']],
        ] as [$label, $amount, $percent]) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $amount);
            $sheet->setCellValue('C'.$row, round($percent / 100, 4));

            $this->styleRow($sheet, $row, 3, $row % 2 === 0);
            $this->applyMoneyFormat($sheet, 'B'.$row, true);
            $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('0%');

            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(36);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(16);
    }

    private function buildSalesSheet(Worksheet $sheet): void
    {
        $sheet->setTitle(__('owner.export_sheet_sales'));
        $sheet->setShowGridlines(false);

        $this->writeTableHeader($sheet, 1, [
            __('owner.table_time'),
            __('owner.table_sale'),
            __('owner.table_cashier'),
            __('owner.table_payment'),
            __('owner.table_items'),
            __('owner.table_total'),
            __('owner.table_status'),
            __('pos.void_reason_done'),
        ]);

        $row = 2;
        $completedRevenue = 0.0;
        $completedCount = 0;

        foreach ($this->data['sales'] as $sale) {
            $isCompleted = $sale->status === Sale::STATUS_COMPLETED;

            if ($isCompleted) {
                $completedRevenue += (float) $sale->total;
                $completedCount++;
            }

            $sheet->setCellValue('A'.$row, $sale->sold_at->format('d.m.Y H:i'));
            $this->writeText($sheet, 'B'.$row, $sale->sale_no);
            $this->writeText($sheet, 'C'.$row, $sale->cashier?->name ?? '—');
            $sheet->setCellValue('D'.$row, $sale->payment_method === Sale::PAYMENT_CASH ? __('owner.payment_cash') : __('owner.payment_card'));
            $sheet->setCellValue('E'.$row, $sale->items_count);
            $sheet->setCellValue('F'.$row, (float) $sale->total);
            $sheet->setCellValue('G'.$row, $isCompleted ? __('owner.sale_status_completed') : __('owner.sale_status_voided'));
            $this->writeText($sheet, 'H'.$row, $sale->void_reason ?? '');

            $this->styleRow($sheet, $row, 8, $row % 2 === 0);
            $this->applyMoneyFormat($sheet, 'F'.$row, true);

            if (! $isCompleted) {
                $sheet->getStyle('G'.$row)->getFont()->getColor()->setRGB(self::COLOR_ACCENT);
            }

            $row++;
        }

        $lastDataRow = max(1, $row - 1);

        // Jami — faqat shu varaqda ko'rsatilgan (limitga tushgan) yakunlangan sotuvlar
        $sheet->setCellValue('E'.$row, __('owner.export_total_rows').' ('.$completedCount.')');
        $sheet->setCellValue('F'.$row, $completedRevenue);
        $sheet->getStyle('E'.$row.':F'.$row)->getFont()->setBold(true);
        $sheet->getStyle('E'.$row.':F'.$row)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_PAPER);
        $this->applyMoneyFormat($sheet, 'F'.$row, true);

        if ($this->data['sales_truncated']) {
            $row++;
            $sheet->mergeCells('A'.$row.':H'.$row);
            $this->writeText($sheet, 'A'.$row, __('owner.export_sales_truncated', [
                'shown' => $this->data['sales']->count(),
                'total' => $this->data['sales_total'],
            ]));
            $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(10)->getColor()->setRGB(self::COLOR_ACCENT);
        }

        $sheet->setAutoFilter('A1:H'.$lastDataRow);
        $sheet->freezePane('A2');

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(16);
        $sheet->getColumnDimension('H')->setWidth(34);

        $this->setupPrintPage($sheet, PageSetup::ORIENTATION_LANDSCAPE, 8);
    }

    private function buildDailySheet(Worksheet $sheet): void
    {
        $sheet->setTitle(__('owner.export_sheet_daily'));
        $sheet->setShowGridlines(false);

        $this->writeTableHeader($sheet, 1, [
            __('owner.table_day'),
            __('owner.report_sales_count'),
            __('owner.table_revenue'),
        ]);

        $row = 2;
        $totalSales = 0;
        $totalRevenue = 0.0;

        foreach ($this->data['dailySales'] as $day) {
            $totalSales += (int) $day->sales_count;
            $totalRevenue += (float) $day->revenue_sum;

            $sheet->setCellValue('A'.$row, Carbon::parse($day->day)->format('d.m.Y'));
            $sheet->setCellValue('B'.$row, (int) $day->sales_count);
            $sheet->setCellValue('C'.$row, (float) $day->revenue_sum);

            $this->styleRow($sheet, $row, 3, $row % 2 === 0);
            $this->applyMoneyFormat($sheet, 'C'.$row, true);

            $row++;
        }

        $sheet->setCellValue('A'.$row, __('owner.export_total'));
        $sheet->setCellValue('B'.$row, $totalSales);
        $sheet->setCellValue('C'.$row, $totalRevenue);
        $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row.':C'.$row)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_PAPER);
        $this->applyMoneyFormat($sheet, 'C'.$row, true);

        $sheet->setAutoFilter('A1:C'.max(1, $row - 1));
        $sheet->freezePane('A2');

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(20);

        $this->setupPrintPage($sheet, PageSetup::ORIENTATION_PORTRAIT, 3);
    }

    private function buildProductsSheet(Worksheet $sheet): void
    {
        $sheet->setTitle(__('owner.export_sheet_products'));
        $sheet->setShowGridlines(false);

        $this->writeTableHeader($sheet, 1, [
            __('owner.table_product'),
            __('owner.table_qty'),
            __('owner.table_revenue'),
        ]);

        $row = 2;
        $totalQuantity = 0;
        $totalRevenue = 0.0;

        foreach ($this->data['topProducts'] as $product) {
            $totalQuantity += (int) $product->quantity_sum;
            $totalRevenue += (float) $product->revenue_sum;

            $this->writeText($sheet, 'A'.$row, $product->product_name_snapshot);
            $sheet->setCellValue('B'.$row, (int) $product->quantity_sum);
            $sheet->setCellValue('C'.$row, (float) $product->revenue_sum);

            $this->styleRow($sheet, $row, 3, $row % 2 === 0);
            $this->applyMoneyFormat($sheet, 'C'.$row, true);

            $row++;
        }

        $sheet->setCellValue('A'.$row, __('owner.export_total_shown_products'));
        $sheet->setCellValue('B'.$row, $totalQuantity);
        $sheet->setCellValue('C'.$row, $totalRevenue);
        $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row.':C'.$row)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_PAPER);
        $this->applyMoneyFormat($sheet, 'C'.$row, true);

        $lastDataRow = max(1, $row - 1);

        if ($this->data['products_truncated']) {
            $row++;
            $sheet->mergeCells('A'.$row.':C'.$row);
            $this->writeText($sheet, 'A'.$row, __('owner.export_products_truncated', [
                'shown' => $this->data['topProducts']->count(),
                'total' => $this->data['products_total'],
            ]));
            $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(10)->getColor()->setRGB(self::COLOR_ACCENT);
        }

        $sheet->setAutoFilter('A1:C'.$lastDataRow);
        $sheet->freezePane('A2');

        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(20);

        $this->setupPrintPage($sheet, PageSetup::ORIENTATION_PORTRAIT, 3);
    }

    private function buildPaymentsSheet(Worksheet $sheet): void
    {
        $sheet->setTitle(__('owner.export_sheet_payments'));
        $sheet->setShowGridlines(false);

        $this->writeTableHeader($sheet, 1, [
            __('owner.table_payment'),
            __('owner.table_total'),
            __('owner.export_share'),
        ]);

        $paymentSplit = $this->data['paymentSplit'];

        $row = 2;
        foreach ([
            [__('owner.payment_cash'), $paymentSplit['cash'], $paymentSplit['cash_percent']],
            [__('owner.payment_card'), $paymentSplit['card'], $paymentSplit['card_percent']],
        ] as [$label, $amount, $percent]) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $amount);
            $sheet->setCellValue('C'.$row, round($percent / 100, 4));

            $this->styleRow($sheet, $row, 3, $row % 2 === 0);
            $this->applyMoneyFormat($sheet, 'B'.$row, true);
            $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('0%');

            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(16);

        $this->setupPrintPage($sheet, PageSetup::ORIENTATION_PORTRAIT, 3);
    }

    private function buildStockSheet(Worksheet $sheet): void
    {
        $sheet->setTitle(__('owner.export_sheet_stock'));
        $sheet->setShowGridlines(false);

        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', __('owner.export_stock_note'));
        $sheet->getStyle('A1')->getFont()->setItalic(true)->setSize(10)->getColor()->setRGB(self::COLOR_SOFT);

        $this->writeTableHeader($sheet, 2, [
            __('owner.table_product'),
            __('owner.export_variant'),
            __('warehouse.table_stock'),
            __('warehouse.variant_reorder'),
        ]);

        $row = 3;
        foreach ($this->data['lowStockVariants'] as $variant) {
            $this->writeText($sheet, 'A'.$row, $variant->product?->name ?? '—');
            $this->writeText($sheet, 'B'.$row, $variant->displayLabel());
            $sheet->setCellValue('C'.$row, $variant->stock_on_hand);
            $sheet->setCellValue('D'.$row, $variant->reorder_level);

            $this->styleRow($sheet, $row, 4, $row % 2 === 0);

            if ($variant->stock_on_hand <= 0) {
                $sheet->getStyle('C'.$row)->getFont()->getColor()->setRGB(self::COLOR_ACCENT);
            }

            $row++;
        }

        $lastDataRow = max(2, $row - 1);

        if ($this->data['low_stock_truncated']) {
            $row++;
            $sheet->mergeCells('A'.$row.':D'.$row);
            $this->writeText($sheet, 'A'.$row, __('owner.export_low_stock_truncated', [
                'shown' => $this->data['lowStockVariants']->count(),
                'total' => $this->data['low_stock_total'],
            ]));
            $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(10)->getColor()->setRGB(self::COLOR_ACCENT);
        }

        $sheet->setAutoFilter('A2:D'.$lastDataRow);
        $sheet->freezePane('A3');

        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(16);

        $this->setupPrintPage($sheet, PageSetup::ORIENTATION_PORTRAIT, 4);
    }

    private function writeSectionTitle(Worksheet $sheet, int $row, string $title, int $span): void
    {
        $lastColumn = chr(ord('A') + $span - 1);

        $sheet->mergeCells('A'.$row.':'.$lastColumn.$row);
        $sheet->setCellValue('A'.$row, $title);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::COLOR_INK);
        $sheet->getStyle('A'.$row)->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COLOR_LINE);
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function writeTableHeader(Worksheet $sheet, int $row, array $labels): void
    {
        foreach ($labels as $index => $label) {
            $column = chr(ord('A') + $index);

            $sheet->setCellValue($column.$row, $label);
            $sheet->getStyle($column.$row)->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_WHITE);
            $sheet->getStyle($column.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_INK);
            $sheet->getStyle($column.$row)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        $lastColumn = chr(ord('A') + count($labels) - 1);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COLOR_INK);
    }

    private function styleRow(Worksheet $sheet, int $row, int $columns, bool $zebra): void
    {
        $lastColumn = chr(ord('A') + $columns - 1);
        $range = 'A'.$row.':'.$lastColumn.$row;

        $sheet->getStyle($range)->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COLOR_LINE);

        if (! $zebra) {
            return;
        }

        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_PAPER);
    }

    private function applyMoneyFormat(Worksheet $sheet, string $cell, bool $isMoney): void
    {
        if (! $isMoney) {
            return;
        }

        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
    }

    /**
     * Foydalanuvchi kiritgan matnni formula emas, oddiy matn sifatida yozadi.
     */
    private function writeText(Worksheet $sheet, string $cell, string $value): void
    {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
    }

    private function setupPrintPage(Worksheet $sheet, string $orientation, int $lastColumnIndex): void
    {
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation($orientation);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setRowsToRepeatAtTopByStartAndEnd(1, 1);

        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.5)->setRight(0.5);
    }

    private function periodLabel(): string
    {
        return $this->data['from']->format('d.m.Y').' — '.$this->data['to']->format('d.m.Y');
    }
}
