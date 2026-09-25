<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Sync runs
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow">
                <form method="POST" action="{{ route('admin.sync-runs.store') }}">
                    @csrf
                    <button type="submit" class="inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-700">
                        Run WooCommerce sync
                    </button>
                </form>
            </div>

            <div class="rounded-lg bg-white p-6 shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Processed</th>
                                <th class="py-2 pr-4">Success</th>
                                <th class="py-2 pr-4">Failed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($syncRuns as $syncRun)
                                <tr>
                                    <td class="py-2 pr-4">{{ $syncRun->status }}</td>
                                    <td class="py-2 pr-4">{{ $syncRun->processed_count }}</td>
                                    <td class="py-2 pr-4">{{ $syncRun->success_count }}</td>
                                    <td class="py-2 pr-4">{{ $syncRun->failed_count }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-gray-500">No sync runs yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
