<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAccount;
use App\Models\AdminAuditLog;
use App\Models\AdminRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', 'string', 'exists:admin_roles,key'], 'status' => ['nullable', Rule::in(['active', 'disabled', 'pending'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = AdminAccount::query();
        if (! empty($data['search'])) {
            $query->where(function ($query) use ($data): void {
                $query->where('name', 'like', '%'.$data['search'].'%')->orWhere('email', 'like', '%'.$data['search'].'%');
            });
        }
        if (! empty($data['role'])) {
            $query->where('role_key', $data['role']);
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        return response()->json(['data' => $query->orderBy('id')->paginate(15)]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower((string) $request->input('email'))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:254', 'unique:admin_accounts,email'], 'role_key' => ['required', 'exists:admin_roles,key'], 'note' => ['nullable', 'string', 'max:1000']]);
        $result = DB::transaction(function () use ($data, $request): array {
            $account = AdminAccount::query()->create($data + ['status' => 'pending']);
            $token = $this->invitation($account);
            $this->audit($request, '创建账号', $account);

            return ['account' => $account->fresh(), 'activationToken' => $token, 'expiresAt' => now()->addHours(24)->toIso8601String()];
        });

        return response()->json(['data' => $result], 201);
    }

    public function update(Request $request, AdminAccount $account): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'role_key' => ['required', 'exists:admin_roles,key'], 'status' => ['required', Rule::in(['active', 'disabled', 'pending'])], 'note' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($account, $data, $request): void {
            AdminRole::query()->where('key', 'admin')->lockForUpdate()->firstOrFail();
            $locked = AdminAccount::query()->lockForUpdate()->findOrFail($account->id);
            abort_if($locked->id === $request->user()->id && ($data['role_key'] !== $locked->role_key || $data['status'] !== $locked->status), 422, '不能修改自己的角色或停用当前账号');
            abort_if($data['status'] === 'pending' && $locked->status !== 'pending', 422, '请通过重置密码生成激活链接');
            abort_if($data['status'] === 'active' && ! $locked->password, 422, '账号需要先通过激活链接设置密码');
            $this->protectLastAdmin($locked, $data['role_key'], $data['status']);
            $locked->fill($data);
            if ($locked->isDirty(['role_key', 'status'])) {
                $locked->token_version++;
            }
            if ($locked->status === 'disabled') {
                $locked->activation_hash = null;
                $locked->activation_expires_at = null;
            }
            $locked->save();
            $this->audit($request, '修改账号', $locked);
        });

        return response()->json(['data' => $account->fresh()]);
    }

    public function destroy(Request $request, AdminAccount $account): JsonResponse
    {
        DB::transaction(function () use ($request, $account): void {
            AdminRole::query()->where('key', 'admin')->lockForUpdate()->firstOrFail();
            $locked = AdminAccount::query()->lockForUpdate()->findOrFail($account->id);
            abort_if($locked->id === $request->user()->id, 422, '不能删除当前账号');
            $this->protectLastAdmin($locked, '', 'disabled');
            $this->audit($request, '删除账号', $locked);
            $locked->delete();
        });

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function resetPassword(Request $request, AdminAccount $account): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $account): array {
            AdminRole::query()->where('key', 'admin')->lockForUpdate()->firstOrFail();
            $locked = AdminAccount::query()->lockForUpdate()->findOrFail($account->id);
            abort_if($locked->id === $request->user()->id, 422, '请由另一位管理员重置当前账号密码');
            $this->protectLastAdmin($locked, $locked->role_key, 'pending');
            $token = $this->invitation($locked);
            $this->audit($request, '重置密码或重发邀请', $locked);

            return ['activationToken' => $token, 'expiresAt' => $locked->activation_expires_at->toIso8601String()];
        });

        return response()->json(['data' => $result]);
    }

    public function logs(): JsonResponse
    {
        return response()->json(['data' => AdminAuditLog::query()->orderByDesc('id')->paginate(20)]);
    }

    private function protectLastAdmin(AdminAccount $account, string $role, string $status): void
    {
        if ($account->role_key === 'admin' && $account->status === 'active' && ($role !== 'admin' || $status !== 'active')) {
            abort_unless(AdminAccount::query()->where('role_key', 'admin')->where('status', 'active')->where('id', '!=', $account->id)->exists(), 422, '至少保留一个启用的超级管理员');
        }
    }

    private function invitation(AdminAccount $account): string
    {
        $token = bin2hex(random_bytes(32));
        $account->update(['status' => 'pending', 'password' => null, 'activation_hash' => hash('sha256', $token), 'activation_expires_at' => now()->addHours(24), 'token_version' => $account->token_version + 1]);

        return $token;
    }

    private function audit(Request $request, string $action, AdminAccount $account): void
    {
        AdminAuditLog::query()->create(['actor_id' => $request->user()->id, 'actor' => $request->user()->email, 'action' => $action, 'subject' => $account->id.' · '.$account->email]);
    }
}
