<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('decide-enrollments', function (User $user) {
            return $user->is_admin;
        });
        Gate::define('post-announcements', function (User $user) {
            return $user->is_admin;
        });
        Gate::define('upload-course-material', function (User $user): bool {
            return (bool) $user->is_admin;
        });
        Gate::define('download-course-material', function (User $user, Course $course): bool {
            return $user->is_admin || $course->students()->whereKey($user->getKey())->exists();
        });
        Gate::define('manage-course-grades', function (User $user): bool {
            return (bool) $user->is_admin;
        });
    }
}
