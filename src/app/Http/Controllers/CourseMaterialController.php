<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CourseMaterialController extends Controller
{
    public function store(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('upload-course-material');

        $validated = $request->validate([
            'material' => ['required', File::types(['pdf'])->max(5 * 1024)],
        ], [
            'material.required' => 'PDF教材を選択してください。',
            'material.uploaded' => 'PDFをアップロードできませんでした。5MB以下のファイルを選択してください。',
            'material.mimes' => 'PDF形式のファイルを選択してください。',
            'material.max' => 'PDF教材は5MB以下にしてください。',
        ]);

        $file = $validated['material'];
        $name = preg_replace('/[[:cntrl:]\/\\\\]/u', '', $file->getClientOriginalName());
        $name = Str::limit(trim($name ?? ''), 255, '') ?: '教材.pdf';
        $disk = Storage::disk('local');
        $newPath = $disk->putFileAs('course-materials', $file, Str::uuid() . '.pdf');

        if ($newPath === false) {
            return back()->withErrors(['material' => 'PDFを保存できませんでした。']);
        }

        try {
            // 同じ授業の差し替えを直列化し、更新時点の旧パスを取得する。
            $oldPath = DB::transaction(function () use ($course, $newPath, $name) {
                $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->getKey());
                $oldPath = $lockedCourse->material_path;
                $lockedCourse->material_path = $newPath;
                $lockedCourse->material_name = $name;

                if (! $lockedCourse->save()) {
                    throw new \RuntimeException('教材情報を保存できませんでした。');
                }

                return $oldPath;
            });
        } catch (Throwable $exception) {
            $this->deleteMaterial($disk, $newPath);
            throw $exception;
        }

        // DBの更新が確定してから旧教材を削除する。
        if ($oldPath !== null) {
            $this->deleteMaterial($disk, $oldPath);
        }

        return redirect()->action([CourseController::class, 'show'], $course)
            ->with('success', 'PDF教材を保存しました。');
    }

    public function download(Course $course): StreamedResponse
    {
        Gate::authorize('download-course-material', $course);

        $disk = Storage::disk('local');
        abort_unless($course->material_path && $disk->exists($course->material_path), 404);

        return $disk->download($course->material_path, $course->material_name, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function deleteMaterial(FilesystemAdapter $disk, string $path): void
    {
        try {
            if (! $disk->delete($path)) {
                Log::warning('PDF教材のファイルを削除できませんでした。', ['path' => $path]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
