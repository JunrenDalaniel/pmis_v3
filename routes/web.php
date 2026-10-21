<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return redirect('/admin');
});

// Named 'login' route required by Laravel's auth middleware redirect.
// Filament uses 'filament.admin.auth.login' but Laravel core looks for 'login'.
Route::get('/login', fn () => redirect()->route('filament.admin.auth.login'))->name('login');

// Inactivity Session Management
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/admin/session/keep-alive', [\App\Http\Controllers\Auth\IdleSessionController::class, 'keepAlive'])->name('admin.session.keep-alive');
    Route::post('/admin/session/idle-logout', [\App\Http\Controllers\Auth\IdleSessionController::class, 'idleLogout'])->name('admin.session.idle-logout');
});


Route::middleware(['auth'])->get('/media-preview/{media}/{filename?}', function (\Spatie\MediaLibrary\MediaCollections\Models\Media $media, ?string $filename = null) {
    // IDOR protection: verify user can access the model that owns this media
    $user = auth()->user();
    $model = $media->model;
    if ($model && method_exists($user, 'getAllowedBranchIds')) {
        $allowed = $user->getAllowedBranchIds();
        if (is_array($allowed) && isset($model->branch_office_id) && !in_array($model->branch_office_id, $allowed)) {
            abort(403, 'Unauthorized: you cannot access media from another branch.');
        }
    }
    if (!file_exists($media->getPath())) {
        abort(404);
    }
    $safeFilename = str_replace(['"', "\r", "\n"], '', (string) $media->file_name);
    return response()->file($media->getPath(), [
        'Content-Type'        => $media->mime_type ?? 'application/octet-stream',
        'Content-Disposition' => 'inline; filename="' . $safeFilename . '"',
    ]);
})->name('media.preview');

Route::middleware(['auth'])->get('/media-download/{media}', function (\Spatie\MediaLibrary\MediaCollections\Models\Media $media) {
    // IDOR protection: verify user can access the model that owns this media
    $user = auth()->user();
    $model = $media->model;
    if ($model && method_exists($user, 'getAllowedBranchIds')) {
        $allowed = $user->getAllowedBranchIds();
        if (is_array($allowed) && isset($model->branch_office_id) && !in_array($model->branch_office_id, $allowed)) {
            abort(403, 'Unauthorized: you cannot access media from another branch.');
        }
    }
    if (!file_exists($media->getPath())) {
        abort(404);
    }
    $safeFilename = str_replace(['"', "\r", "\n"], '', (string) $media->file_name);
    return response()->download($media->getPath(), $safeFilename);
})->name('media.download');

Route::middleware(['auth'])->get('/members/{record}/print', function (\App\Models\Member $record) {
    $user = auth()->user();
    if ($user) {
        $allowed = $user->getAllowedBranchIds();
        if (is_array($allowed) && !in_array($record->branch_office_id, $allowed)) {
            abort(403, 'Unauthorized branch access.');
        }
    }
    return view('filament.resources.members.print-member', ['member' => $record]);
})->name('members.print');

// ─── Notification Sound Customization ─────────────────────────────────────────
// Test / Customize page
Route::middleware(['auth'])->get('/admin/test-notification-sound', function () {
    $config = \App\Filament\Widgets\PendingApprovalSoundWidget::loadSoundConfig();
    return view('filament.test-notification-sound', compact('config'));
})->name('test.notification.sound');

// Save preset preference
Route::middleware(['auth'])->post('/admin/save-notification-sound', function (Request $request) {
    $user = auth()->user();
    if (!$user?->hasRole('super_admin') && !$user?->can('Update:Role')) {
        abort(403, 'Unauthorized: only system administrators can update notification sounds.');
    }
    $data = [
        'type'       => 'preset',
        'preset'     => $request->input('preset', 'classic_chime'),
        'custom_url' => null,
    ];
    \Illuminate\Support\Facades\Storage::disk('local')->put('pmis-sound-pref.json', json_encode($data));
    return response()->json(['ok' => true, 'config' => $data]);
})->name('save.notification.sound');

// Upload custom sound file
Route::middleware(['auth'])->post('/admin/upload-notification-sound', function (Request $request) {
    $user = auth()->user();
    if (!$user?->hasRole('super_admin') && !$user?->can('Update:Role')) {
        abort(403, 'Unauthorized: only system administrators can upload notification sounds.');
    }
    // Validate by extension only — Windows WAV files often have MIME audio/x-wav which
    // the strict 'mimes:wav' rule rejects. Using mimetypes covers all variants.
    $request->validate([
        'sound_file' => [
            'required',
            'file',
            'max:4096', // 4 MB max
            function ($attribute, $value, $fail) {
                $allowed = ['mp3', 'wav', 'ogg'];
                $ext = strtolower($value->getClientOriginalExtension());
                if (!in_array($ext, $allowed)) {
                    $fail('Tanggap lang ang MP3, WAV, o OGG na file.');
                }
            },
        ],
    ]);

    try {
        $file     = $request->file('sound_file');
        $ext      = strtolower($file->getClientOriginalExtension());
        $filename = 'notification-sound.' . $ext;

        // Ensure sounds directory exists
        if (!is_dir(public_path('sounds'))) {
            mkdir(public_path('sounds'), 0755, true);
        }

        $file->move(public_path('sounds'), $filename);
        $url = asset('sounds/' . $filename);

        $data = [
            'type'       => 'custom',
            'preset'     => null,
            'custom_url' => $url,
        ];
        \Illuminate\Support\Facades\Storage::disk('local')->put('pmis-sound-pref.json', json_encode($data));

        return response()->json(['ok' => true, 'url' => $url, 'config' => $data]);

    } catch (\Throwable $e) {
        return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
})->name('upload.notification.sound');

Route::middleware(['auth'])->get('/members/download-template', function () {
    abort_unless(auth()->user()?->can('Create:Member') || auth()->user()?->can('ViewAny:Member'), 403, 'Unauthorized.');

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Members Template');

    $headers = [
        'Member ID (Leave blank to auto-generate)',
        'CID',
        'First Name *',
        'Middle Name',
        'Last Name *',
        'Suffix',
        'Date of Birth (YYYY-MM-DD)',
        'Sex (Male/Female)',
        'Civil Status (Single/Married/Widowed)',
        'TIN',
        'Email Address',
        'Phone Number',
        'Assigned Branch (e.g. BUTUAN)',
        'Complete Address',
    ];

    $sheet->fromArray([$headers], null, 'A1');

    // Header styling: Emerald green
    $sheet->getStyle('A1:N1')->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 11,
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '15803D'],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(28);

    // Sample row
    $sampleData = [
        '',
        'CID-100234',
        'Juan',
        'Santos',
        'Dela Cruz',
        'Jr.',
        '1990-05-15',
        'Male',
        'Married',
        '123-456-789-000',
        'juan.delacruz@example.com',
        '09171234567',
        'BUTUAN',
        'Purok 1, Brgy. Libertad, Butuan City',
    ];

    $sheet->fromArray([$sampleData], null, 'A2');

    foreach (range('A', 'N') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    return response()->streamDownload(function () use ($spreadsheet) {
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
    }, 'Members_Import_Template.xlsx', [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
})->name('members.download-template');

// ─── Reports Center Routes ──────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/reports/print', function (Request $request) {
        abort_unless(auth()->user()?->can('ViewAny:LoanApplication'), 403, 'Unauthorized report access.');
        $reportType = (string) $request->query('report_type', 'loan_release');
        $filters = $request->all();
        $data = \App\Services\ReportExportService::getReportData($reportType, $filters, auth()->user());
        return view('filament.reports.printable-report', ['data' => $data]);
    })->name('reports.print');

    Route::get('/admin/reports/export-excel', function (Request $request) {
        abort_unless(auth()->user()?->can('ViewAny:LoanApplication'), 403, 'Unauthorized report access.');
        $reportType = (string) $request->query('report_type', 'loan_release');
        $filters = $request->all();
        return \App\Services\ReportExportService::exportToExcel($reportType, $filters, auth()->user());
    })->name('reports.export-excel');

    Route::get('/admin/reports/export-pdf', function (Request $request) {
        abort_unless(auth()->user()?->can('ViewAny:LoanApplication'), 403, 'Unauthorized report access.');
        $reportType = (string) $request->query('report_type', 'loan_release');
        $filters = $request->all();
        return \App\Services\ReportExportService::exportToPdf($reportType, $filters, auth()->user());
    })->name('reports.export-pdf');

    Route::get('/admin/reports/deficiency-slip/{record}', function (\App\Models\LoanApplication $record) {
        $user = auth()->user();
        if ($user && method_exists($user, 'getAllowedBranchIds')) {
            $allowed = $user->getAllowedBranchIds();
            if (is_array($allowed) && !in_array($record->branch_office_id, $allowed)) {
                abort(403, 'Unauthorized branch access.');
            }
        }
        $record->load(['member', 'branchOffice', 'realEstateCollaterals', 'chattelCollaterals']);
        return view('filament.reports.deficiency-slip', ['record' => $record]);
    })->name('reports.deficiency-slip');

    Route::get('/admin/reports/vehicle-renewal-notice/{record}', function (\App\Models\ChattelCollateral $record) {
        $user = auth()->user();
        $record->load(['loanApplication.member', 'loanApplication.branchOffice']);
        if ($user && method_exists($user, 'getAllowedBranchIds')) {
            $allowed = $user->getAllowedBranchIds();
            $branchId = $record->loanApplication?->branch_office_id;
            if (is_array($allowed) && $branchId && !in_array($branchId, $allowed)) {
                abort(403, 'Unauthorized branch access.');
            }
        }
        return view('filament.reports.vehicle-renewal-notice', ['record' => $record]);
    })->name('reports.vehicle-renewal-notice');

    // ─── Mortgage Transmittal Routes ─────────────────────────────────────────
    Route::get('/admin/transmittals/generate-print', function (Request $request) {
        abort_unless(auth()->user()?->can('ViewAny:LoanApplication'), 403, 'Unauthorized transmittal access.');
        $loanIds = array_filter(explode(',', (string) $request->query('ids', '')));
        $loansQuery = \App\Models\LoanApplication::with(['member', 'branchOffice', 'realEstateCollaterals', 'chattelCollaterals'])
            ->whereIn('id', $loanIds);

        $user = auth()->user();
        if ($user && method_exists($user, 'getAllowedBranchIds')) {
            $allowed = $user->getAllowedBranchIds();
            if (is_array($allowed)) {
                $loansQuery->whereIn('branch_office_id', $allowed);
            }
        }

        $loans = $loansQuery->get();

        if ($loans->isEmpty()) {
            abort(404, 'Walang nahanap na loans para sa transmittal o walang access.');
        }

        $options = [
            'transmittal_date' => $request->query('date', now()->format('Y-m-d')),
            'checked_by'       => $request->query('checked_by', 'BOOKKEEPER'),
            'approved_by'      => $request->query('approved_by', $request->query('noted_by', 'BRANCH MANAGER')),
            'courier_details'  => $request->query('courier', 'Liaison Officer / Hand Carried'),
        ];

        $firstLoan = $loans->first();
        $isRem = ! (str_contains(strtolower((string) $firstLoan->collateral_type), 'chattel') || str_contains(strtolower((string) $firstLoan->collateral_type), 'cm'));
        $controlNo = 'TR-' . ($isRem ? 'REM' : 'CM') . '-' . strtoupper(date('Ymd')) . '-' . strtoupper(\Illuminate\Support\Str::random(6));

        // Build mock object for view
        $items = $loans->map(function ($loan) use ($isRem) {
            $secType = $isRem ? 'REM' : 'CM';
            $bName = $loan->member?->full_name ?? '—';
            $owner = $isRem
                ? ($loan->realEstateCollaterals->first()?->title_owner_name ?: $bName)
                : ($loan->chattelCollaterals->first()?->owner_name ?: $bName);
            $isSpa = strcasecmp(trim((string) $owner), trim((string) $bName)) !== 0;
            $amt = (float) $loan->requested_amount;
            $fees = \App\Services\MortgageTransmittalService::computeFees($amt, $secType, $isSpa);

            return (object) [
                'loan_account_no'     => $loan->loan_account_no ?: $loan->application_no,
                'date_granted'        => $loan->date_granted,
                'maturity_date'       => $loan->maturity_date,
                'borrower_name'       => $bName,
                'registered_owner'    => $owner,
                'loan_amount'         => $amt,
                'security_type'       => $secType,
                'ho_processing_fee'   => $fees['ho_processing_fee'],
                'bir_dst_fee'         => $fees['bir_dst_fee'],
                'rod_fee'             => $fees['rod_fee'],
                'lto_fee'             => $fees['lto_fee'] ?? 0.00,
                'misc_fee'            => $fees['misc_fee'],
                'spa_fee'             => $fees['spa_fee'],
                'affidavit_fee'       => 0.00,
                'additional_fee'      => 0.00,
                'total_expense'       => $fees['total_expense'],
                'documents_attached'  => \App\Services\MortgageTransmittalService::buildDefaultDocumentsAttached($loan, $secType),
            ];
        });

        $transmittal = (object) [
            'transmittal_no'    => $controlNo,
            'branchOffice'      => $firstLoan->branchOffice,
            'collateral_type'   => $isRem ? 'real_estate_mortgage' : 'chattel_mortgage',
            'transmittal_date'  => $options['transmittal_date'],
            'courier_details'   => $options['courier_details'],
            'checked_by_name'   => $options['checked_by'],
            'approved_by_name'  => $options['approved_by'],
            'prepared_by_name'  => auth()->user()?->name ?? 'MORTGAGE DOCUMENT SPECIALIST',
            'items'             => $items,
        ];

        $excelUrl = route('transmittals.generate-excel', $request->all());

        return view('filament.transmittal.print', [
            'transmittal'    => $transmittal,
            'excelExportUrl' => $excelUrl,
        ]);
    })->name('transmittals.generate-print');

    Route::get('/admin/transmittals/generate-excel', function (Request $request) {
        abort_unless(auth()->user()?->can('ViewAny:LoanApplication'), 403, 'Unauthorized transmittal access.');
        $loanIds = array_filter(explode(',', (string) $request->query('ids', '')));
        $loansQuery = \App\Models\LoanApplication::with(['member', 'branchOffice', 'realEstateCollaterals', 'chattelCollaterals'])
            ->whereIn('id', $loanIds);

        $user = auth()->user();
        if ($user && method_exists($user, 'getAllowedBranchIds')) {
            $allowed = $user->getAllowedBranchIds();
            if (is_array($allowed)) {
                $loansQuery->whereIn('branch_office_id', $allowed);
            }
        }

        $loans = $loansQuery->get();

        if ($loans->isEmpty()) {
            abort(404, 'Walang nahanap na loans para sa transmittal o walang access.');
        }

        $options = [
            'transmittal_date' => $request->query('date', now()->format('Y-m-d')),
            'checked_by'       => $request->query('checked_by', 'BOOKKEEPER'),
            'approved_by'      => $request->query('approved_by', $request->query('noted_by', 'BRANCH MANAGER')),
            'courier_details'  => $request->query('courier', 'Liaison Officer / Hand Carried'),
        ];

        return \App\Services\MortgageTransmittalService::generateExcelFromLoans($loans, $options);
    })->name('transmittals.generate-excel');

    Route::get('/admin/transmittals/session-print', function () {
        abort_unless(auth()->user()?->can('ViewAny:LoanApplication'), 403, 'Unauthorized transmittal access.');
        $data = session()->get('transmittal_preview_data');
        if (! $data) {
            abort(404, 'No transmittal session data found.');
        }

        $items = collect($data['items'] ?? [])->map(function ($item) {
            return (object) [
                'loan_account_no'     => $item['loan_account_no'] ?? '—',
                'date_granted'        => $item['date_granted'] ?? null,
                'maturity_date'       => $item['maturity_date'] ?? null,
                'borrower_name'       => $item['borrower_name'] ?? '—',
                'registered_owner'    => $item['registered_owner'] ?? ($item['borrower_name'] ?? '—'),
                'loan_amount'         => (float) str_replace(',', '', (string) ($item['loan_amount'] ?? 0)),
                'security_type'       => $item['security_type'] ?? 'REM',
                'ho_processing_fee'   => (float) str_replace(',', '', (string) ($item['ho_processing_fee'] ?? 1000)),
                'bir_dst_fee'         => (float) str_replace(',', '', (string) ($item['bir_dst_fee'] ?? 0)),
                'rod_fee'             => (float) str_replace(',', '', (string) ($item['rod_fee'] ?? 0)),
                'lto_fee'             => (float) str_replace(',', '', (string) ($item['lto_fee'] ?? 0)),
                'misc_fee'            => (float) str_replace(',', '', (string) ($item['misc_fee'] ?? 580)),
                'spa_fee'             => (float) str_replace(',', '', (string) ($item['spa_fee'] ?? 0)),
                'affidavit_fee'       => (float) str_replace(',', '', (string) ($item['affidavit_fee'] ?? 0)),
                'additional_fee'      => (float) str_replace(',', '', (string) ($item['additional_fee'] ?? 0)),
                'total_expense'       => (float) str_replace(',', '', (string) ($item['total_expense'] ?? 0)),
                'documents_attached'  => $item['documents_attached'] ?? '—',
            ];
        });

        $transmittal = (object) [
            'transmittal_no'    => $data['transmittal_no'] ?? 'TR-PREVIEW',
            'branchOffice'      => (object) ['name' => $data['branch_name'] ?? 'ALL BRANCHES'],
            'collateral_type'   => $data['collateral_type'] ?? 'real_estate_mortgage',
            'transmittal_date'  => $data['transmittal_date'] ?? now()->format('Y-m-d'),
            'courier_details'   => $data['courier_details'] ?? '',
            'checked_by_name'   => ! empty($data['checked_by']) ? $data['checked_by'] : null,
            'approved_by_name'  => ! empty($data['approved_by']) ? $data['approved_by'] : null,
            'prepared_by_name'  => auth()->user()?->name ?? 'MORTGAGE DOCUMENT SPECIALIST',
            'items'             => $items,
        ];

        return view('filament.transmittal.print', [
            'transmittal' => $transmittal,
        ]);
    })->name('transmittals.session-print');
});

// Dedicated archive download route — avoids Livewire AJAX binary response collision
Route::middleware(['auth'])->get('/admin/loans/{record}/download-archive', function (\App\Models\LoanApplication $record) {
    // 1. IDOR & Branch access verification
    $user = auth()->user();
    if ($user && method_exists($user, 'getAllowedBranchIds')) {
        $allowed = $user->getAllowedBranchIds();
        if (is_array($allowed) && ! in_array($record->branch_office_id, $allowed)) {
            abort(403, 'Unauthorized branch access.');
        }
    }

    $memberNo = $record->member?->member_no ?? 'MEMBER-GENERAL';
    $appNo    = $record->application_no ?? 'LN-GENERAL';
    $password = $memberNo;

    $safeMemberNo = preg_replace('/[^a-zA-Z0-9_-]/', '-', $memberNo);
    $safeAppNo    = preg_replace('/[^a-zA-Z0-9_-]/', '-', $appNo);

    $basePath   = storage_path("app/private/{$safeMemberNo}/{$safeAppNo}");
    $mediaFiles = $record->getMedia('*');
    $hasBasePath = file_exists($basePath);

    if (! $hasBasePath && $mediaFiles->isEmpty()) {
        abort(404, "No document files found for application {$appNo}.");
    }

    $tempZipPath = storage_path("app/private/temp_{$safeAppNo}_" . time() . ".zip");
    $zip = new \ZipArchive();

    if ($zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        abort(500, 'Unable to generate zip archive file.');
    }

    $zip->setPassword($password);

    $borrowerName  = $record->member?->full_name ?? $safeAppNo;
    $cleanMemberNo = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $memberNo);
    $cleanName     = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', (string) $borrowerName);
    $cleanName     = preg_replace('/,{2,}/', ',', trim($cleanName));
    $folderName    = "{$cleanMemberNo}_{$cleanName}";

    $fileCount = 0;
    $addedFiles = [];

    // 1. Add files from basePath if directory exists
    if ($hasBasePath) {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (! $file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($basePath) + 1);
                $zipEntryName = "{$folderName}/" . str_replace('\\', '/', $relativePath);

                $zip->addFile($filePath, $zipEntryName);
                if (defined('\ZipArchive::EM_AES_256')) {
                    $zip->setEncryptionName($zipEntryName, \ZipArchive::EM_AES_256);
                }
                $addedFiles[basename($filePath)] = true;
                $fileCount++;
            }
        }
    }

    // 2. Add files from Spatie media collection if not yet added
    foreach ($mediaFiles as $media) {
        $mediaPath = $media->getPath();
        if (file_exists($mediaPath) && ! isset($addedFiles[$media->file_name])) {
            $zipEntryName = "{$folderName}/" . $media->file_name;
            $zip->addFile($mediaPath, $zipEntryName);
            if (defined('\ZipArchive::EM_AES_256')) {
                $zip->setEncryptionName($zipEntryName, \ZipArchive::EM_AES_256);
            }
            $fileCount++;
        }
    }

    $zip->close();

    if ($fileCount === 0) {
        if (file_exists($tempZipPath)) {
            unlink($tempZipPath);
        }
        abort(404, "No document files found for application {$appNo}.");
    }

    $downloadFileName = "{$cleanMemberNo}_{$cleanName}";
    $safeDownloadFileName = str_replace(['"', "\r", "\n"], '', $downloadFileName);

    try {
        return response()->download($tempZipPath, "{$safeDownloadFileName}.zip")->deleteFileAfterSend(true);
    } catch (\Throwable $e) {
        if (file_exists($tempZipPath)) {
            unlink($tempZipPath);
        }
        throw $e;
    }
})->name('loans.download-archive');

// Graceful fallback for any browser navigation / GET to Livewire update endpoint
Route::get('/{prefix}/update', function () {
    return redirect()->to('/admin');
})->where('prefix', 'livewire.*');



