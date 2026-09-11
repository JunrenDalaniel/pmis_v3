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

Route::middleware(['auth'])->get('/private-file-serve', function (Request $request) {
    $path = $request->query('path');
    if (!$path || !Storage::disk('local')->exists($path)) {
        abort(404);
    }
    return Storage::disk('local')->response($path);
})->name('filament.private-file.serve');

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




