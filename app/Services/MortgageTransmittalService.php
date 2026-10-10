<?php

namespace App\Services;

use App\Models\LoanApplication;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MortgageTransmittalService
{
    /**
     * Compute statutory and cooperative mortgage fees.
     */
    public static function computeFees(float $loanAmount, string $securityType = 'REM', bool $isSpa = false, ?float $customMisc = null): array
    {
        $isCm = ($securityType === 'CM' || strcasecmp($securityType, 'chattel_mortgage') === 0 || str_contains(strtolower($securityType), 'chattel'));

        $hoProcessing = $isCm ? 500.00 : 1000.00;
        $birDst = self::computeDST($loanAmount);
        $rodFee = $isCm ? 1000.00 : self::computeROD($loanAmount);
        $ltoFee = $isCm ? 1500.00 : 0.00;
        $miscFee = $isCm ? 0.00 : ($customMisc ?? 580.00);
        $spaFee = $isCm ? 0.00 : ($isSpa ? 626.32 : 0.00);
        $affidavitFee = 0.00;
        $additionalFee = 0.00;
        $cancellationFee = 0.00;

        $totalExpense = $hoProcessing + $birDst + $rodFee + $ltoFee + $miscFee + $spaFee + $affidavitFee + $additionalFee + $cancellationFee;

        return [
            'ho_processing_fee' => $hoProcessing,
            'bir_dst_fee'       => $birDst,
            'rod_fee'           => $rodFee,
            'lto_fee'           => $ltoFee,
            'misc_fee'          => $miscFee,
            'spa_fee'           => $spaFee,
            'affidavit_fee'     => $affidavitFee,
            'additional_fee'    => $additionalFee,
            'cancellation_fee'  => $cancellationFee,
            'total_expense'     => round($totalExpense, 2),
        ];
    }

    /**
     * BIR DST (Form 2000) = ROUND((Loan / 5000) * 20 + 20, 2)
     */
    public static function computeDST(float $amount): float
    {
        if ($amount <= 0) {
            return 0.00;
        }
        return round(($amount / 5000) * 20 + 20, 2);
    }

    /**
     * Cooperative Statutory ROD tiered schedule for Real Estate Mortgage.
     */
    public static function computeROD(float $amount): float
    {
        if ($amount <= 0) {
            return 0.00;
        }
        if ($amount <= 250000) {
            return 1615.28;
        }
        if ($amount <= 350000) {
            $step = (int) ceil(($amount - 250000) / 10000);
            $lookup = [
                1 => 168, 2 => 288, 3 => 408, 4 => 492, 5 => 576,
                6 => 660, 7 => 774, 8 => 828, 9 => 912, 10 => 996,
            ];
            $add = $lookup[$step] ?? 996;
            return round(1605.28 + $add + 10, 2);
        }
        if ($amount <= 750000) {
            $step = (int) ceil(($amount - 350000) / 10000);
            return round(1605.28 + (996 + $step * 60) * 1.01, 2);
        }

        $step = (int) ceil(($amount - 750000) / 20000);
        return round(1605.28 + (3396 + $step * 90) * 1.01, 2);
    }

    /**
     * Chattel Mortgage / LTO / PPSR baseline fee calculation.
     */
    public static function computeLtoCM(float $amount): float
    {
        if ($amount <= 0) {
            return 0.00;
        }
        return round(1200.00 + ($amount * 0.002), 2);
    }

    /**
     * Generate default documents attached list based on loan details.
     */
    public static function buildDefaultDocumentsAttached(LoanApplication $loan, string $securityType = 'REM'): string
    {
        $docs = [];

        if ($securityType === 'REM') {
            $docs[] = '4 SETS REM';
            $titles = $loan->realEstateCollaterals->pluck('title_number')->filter()->values();
            if ($titles->isNotEmpty()) {
                $docs[] = 'ORIG TCT/OCT W/ TITLE # ' . $titles->implode(', ');
            } else {
                $docs[] = 'ORIG TITLE';
            }
            $docs[] = 'CTC TAX DEC';
            $docs[] = 'TAX CLEARANCE';
            
            $owner = $loan->realEstateCollaterals->first()?->title_owner_name;
            $borrower = $loan->member?->full_name;
            if ($owner && $borrower && strcasecmp(trim($owner), trim($borrower)) !== 0) {
                $docs[] = 'SPA';
            }
            $docs[] = "PHOTOCOPY VALID ID'S";
            $docs[] = 'PN & SWORN UNDERTAKING';
        } else {
            $docs[] = '4 SETS CHATTEL MORTGAGE';
            $vehicles = $loan->chattelCollaterals->map(function ($c) {
                return ($c->plate_no ?: $c->mv_file_no) ?: 'VEHICLE';
            })->filter()->values();
            if ($vehicles->isNotEmpty()) {
                $docs[] = 'ORIG OR/CR (PLATE/MV: ' . $vehicles->implode(', ') . ')';
            } else {
                $docs[] = 'ORIG OR/CR';
            }
            $docs[] = 'STENCIL SHEET (ENGINE & CHASSIS)';
            $docs[] = 'LTO VERIFICATION';
            $docs[] = "PHOTOCOPY VALID ID'S";
            $docs[] = 'PN & SWORN UNDERTAKING';
        }

        return implode(', ', $docs);
    }

    /**
     * Direct 1-Click Export to Official Excel without saving to database.
     *
     * @param iterable $loans Array or Collection of LoanApplication models or custom line items
     * @param array $options [transmittal_date, noted_by, courier_details, branch_name, collateral_type]
     */
    public static function generateExcelFromLoans($loans, array $options = []): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Mortgage Transmittal');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);
        $sheet->setShowGridLines(true);

        // Page Setup: Landscape, Folio (8.5 x 13 in), Fit to 1 page wide
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_FOLIO);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);

        // Print Margins
        $sheet->getPageMargins()->setTop(0.4);
        $sheet->getPageMargins()->setBottom(0.4);
        $sheet->getPageMargins()->setLeft(0.5);
        $sheet->getPageMargins()->setRight(0.4);

        $loansCollection = collect($loans);
        $firstLoan = $loansCollection->first();

        $branchName = strtoupper($options['branch_name'] ?? ($firstLoan instanceof LoanApplication ? ($firstLoan->branchOffice?->name ?? 'ALL BRANCHES') : ($firstLoan['branch_name'] ?? 'ALL BRANCHES')));
        
        $colType = $options['collateral_type'] ?? ($firstLoan instanceof LoanApplication ? $firstLoan->collateral_type : ($firstLoan['collateral_type'] ?? 'real_estate_mortgage'));
        $isRem = ! (str_contains(strtolower((string) $colType), 'chattel') || str_contains(strtolower((string) $colType), 'cm'));

        $collateralTitle = $isRem ? 'REAL ESTATE MORTGAGE' : 'CHATTEL MORTGAGE';
        $secType = $isRem ? 'REM' : 'CM';
        $rodOrLto = $isRem ? 'ROD FEE' : 'LTO FEE';

        $transmittalDate = now()->format('F d, Y');
        if (! empty($options['transmittal_date'])) {
            try {
                $transmittalDate = \Carbon\Carbon::parse($options['transmittal_date'])->format('F d, Y');
            } catch (\Throwable $e) {
                $transmittalDate = (string) $options['transmittal_date'];
            }
        }

        $controlNo = ! empty($options['transmittal_no'])
            ? (string) $options['transmittal_no']
            : ('TR-' . ($isRem ? 'REM' : 'CM') . '-' . strtoupper(date('Ymd')) . '-' . rand(1000, 9999));

        // ==========================================
        // 1. HEADER SECTION (Centered across Columns B to R)
        // ==========================================
        // Row 2: Organization Name
        $sheet->setCellValue('B2', 'ORO INTEGRATED COOPERATIVE');
        $sheet->mergeCells('B2:S2');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('15803D'));
        $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(2)->setRowHeight(22);

        // Row 3: Branch Name
        $sheet->setCellValue('B3', $branchName . ' BRANCH');
        $sheet->mergeCells('B3:S3');
        $sheet->getStyle('B3')->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('000000'));
        $sheet->getStyle('B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // Row 4: Transmittal Title
        $sheet->setCellValue('B4', 'TRANSMITTAL ON MORTGAGE PROCESSING (' . $collateralTitle . ')');
        $sheet->mergeCells('B4:S4');
        $sheet->getStyle('B4')->getFont()->setBold(true)->setSize(11)->setUnderline(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A8A'));
        $sheet->getStyle('B4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(4)->setRowHeight(18);

        // Row 5: Metadata Bar (Collateral, Enclosures, Date, Control No)
        $sheet->setCellValue('B5', 'COLLATERAL: ' . $collateralTitle);
        $sheet->mergeCells('B5:E5');
        $sheet->getStyle('B5')->getFont()->setBold(true)->setSize(8.5)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('374151'));
        $sheet->getStyle('B5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->setCellValue('F5', 'ENCLOSURES: 4 COMPLETE PHYSICAL SETS');
        $sheet->mergeCells('F5:J5');
        $sheet->getStyle('F5')->getFont()->setBold(true)->setSize(8.5)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('15803D'));
        $sheet->getStyle('F5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->setCellValue('K5', 'DATE: ' . strtoupper($transmittalDate));
        $sheet->mergeCells('K5:N5');
        $sheet->getStyle('K5')->getFont()->setBold(true)->setSize(8.5)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('000000'));
        $sheet->getStyle('K5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->setCellValue('O5', 'CONTROL NO: ' . $controlNo);
        $sheet->mergeCells('O5:S5');
        $sheet->getStyle('O5')->getFont()->setBold(true)->setSize(8.5)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A8A'));
        $sheet->getStyle('O5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(5)->setRowHeight(18);

        // Row 6: Spacer
        $sheet->getRowDimension(6)->setRowHeight(6);

        // ==========================================
        // 2. TABLE HEADERS (Rows 7 & 8)
        // ==========================================
        $headers = [
            'B7' => 'NO.',
            'C7' => 'LOAN ACCOUNT #',
            'D7' => 'TERM SCHEDULE',
            'F7' => "BORROWER'S NAME",
            'G7' => 'REGISTERED OWNER',
            'H7' => 'LOAN AMOUNT (PHP)',
            'I7' => 'SEC.',
            'J7' => 'HO FEE',
            'K7' => 'BIR DST',
            'L7' => 'ROD FEE',
            'M7' => 'LTO FEE',
            'N7' => 'MISC / ENV',
            'O7' => 'SPA',
            'P7' => 'AFF',
            'Q7' => "ADD'L EXP.",
            'R7' => 'TOTAL EXP.',
            'S7' => 'DOCUMENTS ATTACHED / REMARKS',
        ];

        foreach ($headers as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        $sheet->setCellValue('D8', 'GRANTED');
        $sheet->setCellValue('E8', 'MATURITY');

        $sheet->mergeCells('B7:B8');
        $sheet->mergeCells('C7:C8');
        $sheet->mergeCells('D7:E7');
        $sheet->mergeCells('F7:F8');
        $sheet->mergeCells('G7:G8');
        $sheet->mergeCells('H7:H8');
        $sheet->mergeCells('I7:I8');
        $sheet->mergeCells('J7:J8');
        $sheet->mergeCells('K7:K8');
        $sheet->mergeCells('L7:L8');
        $sheet->mergeCells('M7:M8');
        $sheet->mergeCells('N7:N8');
        $sheet->mergeCells('O7:O8');
        $sheet->mergeCells('P7:P8');
        $sheet->mergeCells('Q7:Q8');
        $sheet->mergeCells('R7:R8');
        $sheet->mergeCells('S7:S8');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 8.5],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ];
        $sheet->getStyle('B7:R8')->applyFromArray($headerStyle);
        $sheet->getRowDimension(7)->setRowHeight(22);
        $sheet->getRowDimension(8)->setRowHeight(18);

        // ==========================================
        // 3. DATA ROWS (Starting Row 9)
        // ==========================================
        $currentRow = 9;
        $idx = 1;

        // Custom dash format for 0-amount: shows standard currency or dash when 0
        $dashNumberFormat = '#,##0.00;(#,##0.00);"—"';

        foreach ($loansCollection as $item) {
            if ($item instanceof LoanApplication) {
                $item->loadMissing(['member', 'realEstateCollaterals', 'chattelCollaterals', 'branchOffice']);
                $bName = $item->member?->full_name ?? '—';
                $owner = $isRem
                    ? ($item->realEstateCollaterals->first()?->title_owner_name ?: $bName)
                    : ($item->chattelCollaterals->first()?->owner_name ?: $bName);

                $amt = (float) $item->requested_amount;
                $fees = self::computeFees($amt, $secType, false);

                $rowLoanAccount = $item->loan_account_no ?: ($item->application_no ?? '—');
                $rowDateGranted = $item->date_granted ? $item->date_granted->format('Y-m-d') : '—';
                $rowMaturityDate = $item->maturity_date ? $item->maturity_date->format('Y-m-d') : '—';
                $rowBorrower = $bName;
                $rowOwner = $owner ?: $bName;
                $rowAmount = $amt;
                $rowHoFee = (float) $fees['ho_processing_fee'];
                $rowBirFee = (float) $fees['bir_dst_fee'];
                $rowRodFee = (float) $fees['rod_fee'];
                $rowLtoFee = (float) ($fees['lto_fee'] ?? 0.00);
                $rowMiscFee = (float) $fees['misc_fee'];
                $rowSpaFee = (float) ($fees['spa_fee'] ?? 0.00);
                $rowAffFee = 0.00;
                $rowAddlFee = 0.00;
                $rowTotalExp = (float) $fees['total_expense'];
                $rowDocs = self::buildDefaultDocumentsAttached($item, $secType);
            } else {
                // Array item from generator page form
                $formatRowDate = function ($v) {
                    if (empty($v) || $v === '—') return '—';
                    try {
                        return \Carbon\Carbon::parse($v)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        return (string) $v;
                    }
                };
                $rowLoanAccount = $item['loan_account_no'] ?? '—';
                $rowDateGranted = $formatRowDate($item['date_granted'] ?? null);
                $rowMaturityDate = $formatRowDate($item['maturity_date'] ?? null);
                $rowBorrower = $item['borrower_name'] ?? '—';
                $rowOwner = $item['registered_owner'] ?? $rowBorrower;
                $rowAmount = (float) str_replace(',', '', (string) ($item['loan_amount'] ?? 0));
                $rowHoFee = (float) str_replace(',', '', (string) ($item['ho_processing_fee'] ?? 1000));
                $rowBirFee = (float) str_replace(',', '', (string) ($item['bir_dst_fee'] ?? self::computeDST($rowAmount)));
                $rowRodFee = (float) str_replace(',', '', (string) ($item['rod_fee'] ?? ($isRem ? self::computeROD($rowAmount) : 1000.00)));
                $rowLtoFee = (float) str_replace(',', '', (string) ($item['lto_fee'] ?? ($isRem ? 0 : 1500.00)));
                $rowMiscFee = (float) str_replace(',', '', (string) ($item['misc_fee'] ?? 580));
                $rowSpaFee = (float) str_replace(',', '', (string) ($item['spa_fee'] ?? 0));
                $rowAffFee = (float) str_replace(',', '', (string) ($item['affidavit_fee'] ?? 0));
                $rowAddlFee = (float) str_replace(',', '', (string) ($item['additional_fee'] ?? 0));
                $rowTotalExp = (float) str_replace(',', '', (string) ($item['total_expense'] ?? ($rowHoFee + $rowBirFee + $rowRodFee + $rowLtoFee + $rowMiscFee + $rowSpaFee + $rowAffFee + $rowAddlFee)));
                $rowDocs = $item['documents_attached'] ?? '—';
            }

            $rowSec = ! empty($item['security_type']) ? (string) $item['security_type'] : $secType;

            $sheet->setCellValue('B' . $currentRow, $idx++);
            $sheet->setCellValue('C' . $currentRow, $rowLoanAccount);
            $sheet->setCellValue('D' . $currentRow, $rowDateGranted);
            $sheet->setCellValue('E' . $currentRow, $rowMaturityDate);
            $sheet->setCellValue('F' . $currentRow, $rowBorrower);
            $sheet->setCellValue('G' . $currentRow, $rowOwner);
            $sheet->setCellValue('H' . $currentRow, $rowAmount);
            $sheet->setCellValue('I' . $currentRow, $rowSec);
            $sheet->setCellValue('J' . $currentRow, $rowHoFee);
            $sheet->setCellValue('K' . $currentRow, $rowBirFee);
            $sheet->setCellValue('L' . $currentRow, $rowRodFee);
            $sheet->setCellValue('M' . $currentRow, $rowLtoFee);
            $sheet->setCellValue('N' . $currentRow, $rowMiscFee);
            $sheet->setCellValue('O' . $currentRow, $rowSpaFee);
            $sheet->setCellValue('P' . $currentRow, $rowAffFee);
            $sheet->setCellValue('Q' . $currentRow, $rowAddlFee);
            $sheet->setCellValue('R' . $currentRow, $rowTotalExp);
            $sheet->setCellValue('S' . $currentRow, $rowDocs ?: '—');

            // Formats
            $sheet->getStyle('H' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('J' . $currentRow . ':L' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('M' . $currentRow)->getNumberFormat()->setFormatCode($dashNumberFormat);
            $sheet->getStyle('N' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('O' . $currentRow . ':Q' . $currentRow)->getNumberFormat()->setFormatCode($dashNumberFormat);
            $sheet->getStyle('R' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.00');

            // Alignments & Fonts
            $sheet->getStyle('B' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $currentRow)->getFont()->setBold(true);
            $sheet->getStyle('C' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C' . $currentRow)->getFont()->setBold(true);
            $sheet->getStyle('D' . $currentRow . ':E' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $currentRow)->getFont()->setBold(true);
            $sheet->getStyle('I' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('I' . $currentRow)->getFont()->setBold(true);
            $sheet->getStyle('H' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('H' . $currentRow)->getFont()->setBold(true);
            $sheet->getStyle('J' . $currentRow . ':R' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('R' . $currentRow)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A8A'));
            $sheet->getStyle('S' . $currentRow)->getAlignment()->setWrapText(true);

            // Zebra Striping
            $rowBg = ($idx % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
            $sheet->getStyle('B' . $currentRow . ':S' . $currentRow)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(22);

            $currentRow++;
        }

        $startDataRow = 9;
        $endDataRow = max($currentRow - 1, $startDataRow);

        // ==========================================
        // 4. BATCH TOTAL ROW
        // ==========================================
        $sheet->setCellValue('G' . $currentRow, 'TOTAL BATCH:');
        $sheet->setCellValue('H' . $currentRow, "=SUM(H{$startDataRow}:H{$endDataRow})");
        $sheet->setCellValue('J' . $currentRow, "=SUM(J{$startDataRow}:J{$endDataRow})");
        $sheet->setCellValue('K' . $currentRow, "=SUM(K{$startDataRow}:K{$endDataRow})");
        $sheet->setCellValue('L' . $currentRow, "=SUM(L{$startDataRow}:L{$endDataRow})");
        $sheet->setCellValue('M' . $currentRow, "=SUM(M{$startDataRow}:M{$endDataRow})");
        $sheet->setCellValue('N' . $currentRow, "=SUM(N{$startDataRow}:N{$endDataRow})");
        $sheet->setCellValue('O' . $currentRow, "=SUM(O{$startDataRow}:O{$endDataRow})");
        $sheet->setCellValue('P' . $currentRow, "=SUM(P{$startDataRow}:P{$endDataRow})");
        $sheet->setCellValue('Q' . $currentRow, "=SUM(Q{$startDataRow}:Q{$endDataRow})");
        $sheet->setCellValue('R' . $currentRow, "=SUM(R{$startDataRow}:R{$endDataRow})");

        $sheet->getStyle('G' . $currentRow . ':R' . $currentRow)->getFont()->setBold(true);
        $sheet->getStyle('G' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('H' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('J' . $currentRow . ':L' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('M' . $currentRow)->getNumberFormat()->setFormatCode($dashNumberFormat);
        $sheet->getStyle('N' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('O' . $currentRow . ':Q' . $currentRow)->getNumberFormat()->setFormatCode($dashNumberFormat);
        $sheet->getStyle('R' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('R' . $currentRow)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A8A'));

        $sheet->getStyle('B' . $currentRow . ':S' . $currentRow)->applyFromArray([
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '000000']],
                'left' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
                'right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
        ]);
        $sheet->getRowDimension($currentRow)->setRowHeight(24);

        // ==========================================
        // 5. SIGNATORIES SECTION (Identical to Print Design)
        // ==========================================
        $sigRow = $currentRow + 2;

        // Roles Row
        $sheet->setCellValue('B' . $sigRow, 'PREPARED BY:');
        $sheet->mergeCells("B{$sigRow}:E{$sigRow}");
        $sheet->setCellValue('F' . $sigRow, 'CHECKED BY:');
        $sheet->mergeCells("F{$sigRow}:I{$sigRow}");
        $sheet->setCellValue('J' . $sigRow, 'APPROVED BY:');
        $sheet->mergeCells("J{$sigRow}:N{$sigRow}");
        $sheet->setCellValue('O' . $sigRow, 'RECEIVED & VERIFIED BY (HO):');
        $sheet->mergeCells("O{$sigRow}:S{$sigRow}");

        $sheet->getStyle("B{$sigRow}:S{$sigRow}")->getFont()->setBold(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('000000'));
        $sheet->getRowDimension($sigRow)->setRowHeight(18);

        // Signature Gap Row
        $gapRow = $sigRow + 1;
        $sheet->getRowDimension($gapRow)->setRowHeight(26);

        // Signatory Names Row
        $prepName     = strtoupper(auth()->user()?->name ?? 'MORTGAGE DOCUMENT SPECIALIST');
        $checkedName  = !empty($options['checked_by']) ? strtoupper($options['checked_by']) : '________________________________';
        $approvedName = !empty($options['approved_by']) ? strtoupper($options['approved_by']) : '________________________________';
        $recName      = '________________________________';

        $sigNameRow = $sigRow + 2;
        $sheet->setCellValue('B' . $sigNameRow, $prepName);
        $sheet->mergeCells("B{$sigNameRow}:E{$sigNameRow}");
        $sheet->setCellValue('F' . $sigNameRow, $checkedName);
        $sheet->mergeCells("F{$sigNameRow}:I{$sigNameRow}");
        $sheet->setCellValue('J' . $sigNameRow, $approvedName);
        $sheet->mergeCells("J{$sigNameRow}:N{$sigNameRow}");
        $sheet->setCellValue('O' . $sigNameRow, $recName);
        $sheet->mergeCells("O{$sigNameRow}:S{$sigNameRow}");

        $sheet->getStyle("B{$sigNameRow}:S{$sigNameRow}")->getFont()->setBold(true)->setUnderline(true)->setSize(9.5);
        $sheet->getStyle("B{$sigNameRow}:S{$sigNameRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($sigNameRow)->setRowHeight(18);

        // Signatory Positions Row
        $sigTitleRow = $sigNameRow + 1;
        $sheet->setCellValue('B' . $sigTitleRow, 'Loan Clerk / Mortgage Document Specialist');
        $sheet->mergeCells("B{$sigTitleRow}:E{$sigTitleRow}");
        $sheet->setCellValue('F' . $sigTitleRow, 'Bookkeeper');
        $sheet->mergeCells("F{$sigTitleRow}:I{$sigTitleRow}");
        $sheet->setCellValue('J' . $sigTitleRow, 'Branch Manager');
        $sheet->mergeCells("J{$sigTitleRow}:N{$sigTitleRow}");
        $sheet->setCellValue('O' . $sigTitleRow, 'Mortgage Assessment Specialist (Head Office)');
        $sheet->mergeCells("O{$sigTitleRow}:S{$sigTitleRow}");

        $sheet->getStyle("B{$sigTitleRow}:S{$sigTitleRow}")->getFont()->setBold(true)->setSize(8)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1F2937'));
        $sheet->getStyle("B{$sigTitleRow}:S{$sigTitleRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($sigTitleRow)->setRowHeight(15);

        // Signatory Subnotes Row
        $sigSubRow = $sigTitleRow + 1;
        $sheet->setCellValue('B' . $sigSubRow, '(Transmittal Generator & Packager)');
        $sheet->mergeCells("B{$sigSubRow}:E{$sigSubRow}");
        $sheet->setCellValue('F' . $sigSubRow, '(Accounting & Expense Verification)');
        $sheet->mergeCells("F{$sigSubRow}:I{$sigSubRow}");
        $sheet->setCellValue('J' . $sigSubRow, '(Branch Operations Head)');
        $sheet->mergeCells("J{$sigSubRow}:N{$sigSubRow}");
        $sheet->setCellValue('O' . $sigSubRow, 'Date & Time Received: _________________');
        $sheet->mergeCells("O{$sigSubRow}:S{$sigSubRow}");

        $sheet->getStyle("B{$sigSubRow}:N{$sigSubRow}")->getFont()->setItalic(true)->setSize(7.5)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('4B5563'));
        $sheet->getStyle("O{$sigSubRow}")->getFont()->setBold(true)->setSize(7.5)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('4B5563'));
        $sheet->getStyle("B{$sigSubRow}:S{$sigSubRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($sigSubRow)->setRowHeight(15);

        // ==========================================
        // 6. LEGEND / COMPUTATION RULES BOX
        // ==========================================
        $legendRow = $sigSubRow + 2;
        $sheet->setCellValue('B' . $legendRow, 'COOPERATIVE MORTGAGE COMPUTATION RULES & FEE BREAKDOWN');
        $sheet->mergeCells("B{$legendRow}:R{$legendRow}");
        $sheet->getStyle('B' . $legendRow)->getFont()->setBold(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A8A'));
        $sheet->getStyle('B' . $legendRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("B{$legendRow}:R{$legendRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']]],
        ]);
        $sheet->getRowDimension($legendRow)->setRowHeight(20);

        // 4 Clean Breakdown Summary Boxes
        $boxRow = $legendRow + 1;
        $sheet->setCellValue('B' . $boxRow, "1. Statutory Registration:\n• REM ROD: Tiered schedule (≤₱250k: ₱1,615.28 baseline, +bracket tiers)\n• CM ROD: Fixed ₱1,000.00 (online registration payment)\n• CM LTO: ₱1,500.00 per CR (+₱1,500 if with cancellation of prior mortgage)");
        $sheet->mergeCells("B{$boxRow}:E{$boxRow}");

        $sheet->setCellValue('F' . $boxRow, "2. BIR DST (Form 2000):\n• Formula: (Loan ÷ 5,000 × ₱20) + ₱20\n• Standard NIRC mortgage documentary stamp tax compliance for both REM and CM.");
        $sheet->mergeCells("F{$boxRow}:I{$boxRow}");

        $sheet->setCellValue('J' . $boxRow, "3. Administrative & Ancillary:\n• HO Fee: ₱1,000.00 (REM) | ₱500.00 (CM)\n• Misc & Env: ₱580.00 (REM only; CM is ₱0.00 online)\n• SPA & Affidavits: REM only (₱626.32 / ₱536.32; CM is ₱0.00)\n• Add'l: Food, fuel, travel (REM only; CM is ₱0.00)");
        $sheet->mergeCells("J{$boxRow}:N{$boxRow}");

        $sheet->setCellValue('O' . $boxRow, "4. Custodial Compliance Notes:\n• Complete sets of documents (Titles, Tax Decs, OR/CR, Stencil sheets, IDs) must be verified and cleared prior to transmission.");
        $sheet->mergeCells("O{$boxRow}:S{$boxRow}");

        $sheet->getStyle("B{$boxRow}:S{$boxRow}")->applyFromArray([
            'font' => ['size' => 7.5, 'color' => ['rgb' => '1F2937']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9FAFB']],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
        $sheet->getRowDimension($boxRow)->setRowHeight(68);

        // ==========================================
        // 7. COLUMN WIDTHS (Exact Proportions for Landscape Folio)
        // ==========================================
        $widths = [
            'B' => 5.0,  // NO.
            'C' => 18.0, // LOAN ACCOUNT #
            'D' => 12.0, // GRANTED
            'E' => 12.0, // MATURITY
            'F' => 25.0, // BORROWER'S NAME
            'G' => 23.0, // REGISTERED OWNER
            'H' => 17.0, // LOAN AMOUNT
            'I' => 7.0,  // SEC.
            'J' => 11.5, // HO FEE
            'K' => 11.5, // BIR DST
            'L' => 12.0, // ROD FEE
            'M' => 12.0, // LTO FEE
            'N' => 11.0, // MISC / ENV
            'O' => 10.0, // SPA
            'P' => 10.0, // AFF
            'Q' => 12.0, // ADD'L EXP.
            'R' => 15.0, // TOTAL EXP.
            'S' => 34.0, // DOCUMENTS ATTACHED / REMARKS
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $filename = "{$controlNo}_" . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}