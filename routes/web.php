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


Route::middleware(['auth'])->get('/media-preview/{media}', function (\Spatie\MediaLibrary\MediaCollections\Models\Media $media) {
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
    return response()->file($media->getPath(), [
        'Content-Type'        => $media->mime_type ?? 'application/octet-stream',
        'Content-Disposition' => 'inline; filename="' . $media->file_name . '"',
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
    return response()->download($media->getPath(), $media->file_name);
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
                $allowed = ['mp3', 'wav', 'ogg', 'mpeg'];
                $ext = strtolower($value->getClientOriginalExtension());
                if (!in_array($ext, ['mp3', 'wav', 'ogg'])) {
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

