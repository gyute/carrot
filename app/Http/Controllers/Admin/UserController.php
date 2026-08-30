<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\RetireUser;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RetireUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Support\Departments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roles and departments, editable in place. This is the screen `carrot:promote`
 * exists for on a box with no browser - both write the same two columns.
 */
class UserController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $role = UserRole::tryFrom((string) $request->query('role', ''));

        $users = User::query()
            ->withTrashed()
            ->when($search !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('department', 'like', "%{$search}%")))
            ->when($role !== null, fn ($query) => $query->where('role', $role))
            ->orderBy('username')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => [
                'data' => collect($users->items())->map($this->present(...))->all(),
                'currentPage' => $users->currentPage(),
                'lastPage' => $users->lastPage(),
                'total' => $users->total(),
            ],
            'filters' => ['q' => $search, 'role' => $role?->value],
            'roles' => collect(UserRole::cases())
                ->map(fn (UserRole $case): array => ['value' => $case->value, 'label' => $case->label()])
                ->all(),
            'departments' => Departments::all(),
            'successors' => $this->successors(),
        ]);
    }

    /**
     * A manager needs a department: without one they are the reviewer for
     * nothing, and requests would silently fall through to the admins.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_if($user->trashed(), 422, '退職した利用者の権限は変更できません。');

        $user->forceFill([
            'role' => $request->enum('role', UserRole::class),
            'department' => $request->validated('department') ?: null,
        ])->save();

        return back()->with('status', "{$user->username} を {$user->role->label()} にしました。");
    }

    /**
     * Retires an account: the person's own data goes, what they registered
     * and approved stays under an anonymous row, and the tools they looked
     * after pass to somebody who can still maintain them.
     */
    public function retire(RetireUserRequest $request, User $user, RetireUser $retire): RedirectResponse
    {
        abort_if($user->trashed(), 422, 'すでに退職処理済みです。');
        abort_if($user->is($request->user()), 422, '自分の退職処理はプロフィール画面から行ってください。');

        $successor = $this->successor($request);
        $name = $user->name;
        $handed = $retire->handle($user, $successor);

        return back()->with('status', $handed === null
            ? "{$name} を退職処理しました。所有していたツールは管理者が引き継ぎます。"
            : "{$name} を退職処理しました。所有していたツールは {$handed->name} に引き継がれました。");
    }

    private function successor(RetireUserRequest $request): ?User
    {
        $ulid = $request->validated('successor');

        return is_string($ulid) && $ulid !== ''
            ? User::query()->where('ulid', $ulid)->first()
            : null;
    }

    /**
     * Who a departing person's tools may be handed to.
     *
     * @return array<int, array{ulid: string, name: string}>
     */
    private function successors(): array
    {
        return User::query()
            ->whereIn('role', [UserRole::Manager, UserRole::Admin])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => ['ulid' => $user->ulid, 'name' => $user->name])
            ->all();
    }

    /**
     * @return array{ulid: string, name: string, username: string, email: string, role: string, roleLabel: string, department: string|null, retired: bool, createdAt: string}
     */
    private function present(User $user): array
    {
        return [
            'ulid' => $user->ulid,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role->value,
            'roleLabel' => $user->role->label(),
            'department' => $user->department,
            'retired' => $user->trashed(),
            'createdAt' => $user->created_at?->toIso8601String() ?? '',
        ];
    }
}
