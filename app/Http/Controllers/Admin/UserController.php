<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
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
        ]);
    }

    /**
     * A manager needs a department: without one they are the reviewer for
     * nothing, and requests would silently fall through to the admins.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'role' => $request->enum('role', UserRole::class),
            'department' => $request->validated('department') ?: null,
        ])->save();

        return back()->with('status', "{$user->username} を {$user->role->label()} にしました。");
    }

    /**
     * @return array{ulid: string, name: string, username: string, email: string, role: string, roleLabel: string, department: string|null, createdAt: string}
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
            'createdAt' => $user->created_at?->toIso8601String() ?? '',
        ];
    }
}
