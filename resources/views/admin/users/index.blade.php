<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Quản lý người dùng
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow">
                <p class="text-sm text-gray-600">
                    <strong>Admin</strong>: toàn quyền — import/quản lý sản phẩm, công thức giá, xem tất cả báo giá, thêm/đổi vai trò người dùng.
                    <strong>Sales</strong>: tạo báo giá, chỉ xem/xuất PDF báo giá do chính mình tạo.
                    <strong>Manager</strong>: xem tất cả báo giá (không có quyền quản lý catalog/công thức giá).
                </p>

                <h3 class="mt-6 text-sm font-semibold uppercase tracking-wide text-gray-500">Thêm người dùng mới</h3>

                <form method="POST" action="{{ route('admin.users.store') }}" class="mt-3 grid gap-4 border-b border-gray-100 pb-6 md:grid-cols-5">
                    @csrf

                    <div>
                        <label class="block text-xs font-medium text-gray-500">Tên</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500">Mật khẩu</label>
                        <input type="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500">Nhập lại mật khẩu</label>
                        <input type="password" name="password_confirmation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500">Vai trò</label>
                        <select name="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-5">
                        <button type="submit" class="inline-flex rounded-md bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">
                            Tạo tài khoản
                        </button>
                        <p class="mt-2 text-xs text-gray-400">
                            Tài khoản mới được xác thực email ngay (hệ thống chưa cấu hình gửi mail xác thực) — người dùng đăng nhập được ngay bằng email/mật khẩu vừa tạo.
                        </p>
                    </div>
                </form>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2 pr-4">Tên</th>
                                <th class="py-2 pr-4">Email</th>
                                <th class="py-2 pr-4">Vai trò</th>
                                <th class="py-2 pr-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($users as $user)
                                <tr>
                                    <td class="py-2 pr-4 font-medium text-gray-900">
                                        {{ $user->name }}
                                        @if ($user->id === auth()->id())
                                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">Bạn</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $user->email }}</td>
                                    @if ($user->id === auth()->id())
                                        <td class="py-2 pr-4 text-gray-500" colspan="2">
                                            {{ $user->role }}
                                            <span class="ml-1 text-xs text-gray-400">(nhờ admin khác đổi giúp nếu cần)</span>
                                        </td>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="contents">
                                            @csrf
                                            @method('PATCH')
                                            <td class="py-2 pr-4">
                                                <select name="role" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role }}" @selected($user->role === $role)>{{ $role }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="py-2 pr-4 text-right">
                                                <button type="submit" class="font-medium text-brand-600 hover:underline">Lưu</button>
                                            </td>
                                        </form>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
