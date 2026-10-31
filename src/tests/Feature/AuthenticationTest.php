<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    // 既存のPostgreSQLを使い、テスト中の書き込みはすべて元に戻す。
    use DatabaseTransactions;

    private function registration(array $overrides = []): array
    {
        return array_replace([
            'name' => '登録テスト',
            'email' => 'week7-'.bin2hex(random_bytes(8)).'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_guests_can_view_forms_and_cannot_access_protected_routes(): void
    {
        $this->get('/register')->assertOk()->assertSee('ユーザー登録');
        $this->get('/login')->assertOk()->assertSee('ログイン');
        foreach (['/entries', '/entries/create', '/courses'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/entries', [])->assertRedirect('/login');
        $this->delete('/entries/1')->assertRedirect('/login');
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_registration_logs_in_and_hashes_the_password(): void
    {
        $data = $this->registration();
        $this->post('/register', $data)->assertRedirect('/entries');
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($data['password'], $user->password);
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->get('/entries')->assertOk()->assertSee('登録テストさんの時間割');
        $this->get('/login')->assertRedirect('/entries');
    }

    public function test_registration_validation_and_safe_old_input(): void
    {
        $existing = User::factory()->create();
        $cases = [
            [['name' => ''], 'name', '名前を入力してください'],
            [['email' => ''], 'email', 'メールアドレスを入力してください'],
            [['email' => 'abc'], 'email', 'メールアドレスの形式で入力してください'],
            [['email' => $existing->email], 'email', 'このメールアドレスはすでに登録されています'],
            [['password' => '1234567', 'password_confirmation' => '1234567'], 'password', 'パスワードは8文字以上で入力してください'],
            [['password_confirmation' => 'different'], 'password', 'パスワードが確認用と一致しません'],
            [['password' => ''], 'password', 'パスワードは8文字以上で入力してください'],
        ];
        $count = User::count();
        foreach ($cases as [$overrides, $field, $message]) {
            $data = $this->registration($overrides);
            $this->from('/register')->post('/register', $data)
                ->assertRedirect('/register')
                ->assertSessionHasErrors([$field => $message])
                ->assertSessionHas('_old_input.name', $data['name'])
                ->assertSessionHas('_old_input.email', $data['email'])
                ->assertSessionMissing('_old_input.password')
                ->assertSessionMissing('_old_input.password_confirmation');
            $this->get('/register')->assertSee($message);
            $this->assertGuest();
        }
        $this->assertSame($count, User::count());
    }

    public function test_login_regenerates_session_and_logout_invalidates_it(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $this->get('/login');
        $oldId = session()->getId();
        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect('/entries');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldId, session()->getId());
        $this->get('/entries')->assertSee($user->name.'さんの時間割')->assertSee('ログアウト');
        $this->get('/courses')->assertOk();
        session()->put('logout_test', '削除される値');
        $token = session()->token();
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('logout_test');
        $this->assertGuest();
        $this->assertNotSame($token, session()->token());
        $this->get('/entries')->assertRedirect('/login');
    }

    public function test_failed_login_keeps_only_email(): void
    {
        $user = User::factory()->create();
        foreach (['wrong-password', ''] as $password) {
            $this->post('/login', ['email' => $user->email, 'password' => $password])
                ->assertRedirect('/login')
                ->assertSessionHasErrors(['email' => 'メールアドレスかパスワードが違います'])
                ->assertSessionHas('_old_input.email', $user->email)
                ->assertSessionMissing('_old_input.password');
            $this->assertGuest();
        }
        $this->get('/login')->assertSee($user->email)->assertSee('メールアドレスかパスワードが違います');
    }

    public function test_entries_belong_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::firstOrFail();
        $entry = $owner->entries()->create(['course_id' => $course->id]);

        $this->actingAs($other)->get('/entries')->assertViewHas('entries', fn ($entries) => $entries->isEmpty());
        $this->post('/entries', ['course_id' => $course->id, 'user_id' => $owner->id])
            ->assertRedirect('/entries')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('timetable_entries', ['user_id' => $other->id, 'course_id' => $course->id]);
        $this->get('/entries')->assertViewHas('entries', fn ($entries) => $entries->count() === 1 && $entries->first()->user_id === $other->id);
        $this->post('/entries', ['course_id' => $course->id])->assertSessionHasErrors('course_id');
        $this->actingAs($owner)->delete('/entries/'.$entry->id)->assertRedirect('/entries');
        $this->assertDatabaseMissing('timetable_entries', ['id' => $entry->id]);
    }

    public function test_same_period_is_rejected_for_the_current_user(): void
    {
        $user = User::factory()->create();
        $course = Course::firstOrFail();
        $alternative = Course::where('day', $course->day)->where('period', $course->period)
            ->where('id', '!=', $course->id)->firstOrFail();
        $user->entries()->create(['course_id' => $course->id]);
        $this->actingAs($user)->get('/entries/create')->assertOk();
        $this->post('/entries', ['course_id' => $alternative->id])
            ->assertSessionHasErrors(['course_id' => 'その曜日・時限にはすでに授業が登録されています。']);
        $this->assertDatabaseMissing('timetable_entries', ['user_id' => $user->id, 'course_id' => $alternative->id]);
    }
}
