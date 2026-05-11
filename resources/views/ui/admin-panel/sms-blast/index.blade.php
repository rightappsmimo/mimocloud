<div class="flex flex-col gap-2 p-6 lg:p-8">
    <div class="p-8 backdrop-blur bg-white/70 rounded-xl border border-gray-50">
        <div class="flex flex-row items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-[var(--color-primary-mid-dark)]">SMS Blast History</h1>
                <p class="text-gray-800 text-sm mt-1">View and manage all SMS campaigns</p>
            </div>
            <a href="{{ route('sms_blast.create') }}" class="px-4 text-white py-2 bg-[var(--color-primary-full-dark)] rounded-lg hover:opacity-80 transition-all duration-300">
                <i class="fas fa-plus mr-2"></i>Create New Blast
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
        <div class="bg-white/70 backdrop-blur-lg rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm text-gray-500">Total Blasts</div>
                    <div class="text-2xl font-bold">{{ $stats['total'] ?? 8 }}</div>
                </div>
                <div class="w-12 h-12 text-blue-100 rounded-lg flex items-center justify-center bg-blue-600">
                    <i class="fas fa-paper-plane text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white/70 backdrop-blur-lg rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm text-gray-500">Sent</div>
                    <div class="text-2xl font-bold text-green-600">{{ $stats['sent'] ?? 5 }}</div>
                </div>
                <div class="w-12 h-12 text-green-100 rounded-lg flex items-center justify-center bg-green-600">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white/70 backdrop-blur-lg rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm text-gray-500">Scheduled</div>
                    <div class="text-2xl font-bold text-blue-600">{{ $stats['scheduled'] ?? 3 }}</div>
                </div>
                <div class="w-12 h-12 text-blue-100 rounded-lg flex items-center justify-center bg-blue-600">
                    <i class="fas fa-clock text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white/70 backdrop-blur-lg rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm text-gray-500">Failed</div>
                    <div class="text-2xl font-bold text-red-600">{{ $stats['failed'] ?? 1 }}</div>
                </div>
                <div class="w-12 h-12 text-red-100 rounded-lg flex items-center justify-center bg-red-600">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white/70 backdrop-blur-lg rounded-xl shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex flex-wrap justify-end gap-4">
            <div class="min-w-[200px]">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search blasts..."
                    class="w-80 px-4 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Status</option>
                <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
            <button class="btn-primary">
                <i class="fas fa-search mr-2"></i>Filter
            </button>
        </form>
    </div>

    <div class="bg-white/70 backdrop-blur-lg rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-800 uppercase tracking-wider">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-800 uppercase tracking-wider">Message Preview</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-800 uppercase tracking-wider">Recipients</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-800 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-800 uppercase tracking-wider">Scheduled/Sent</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-800 uppercase tracking-wider">Progress</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-800 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($blasts as $blast)
                <tr class="table-row hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium">{{ Str::limit($blast->title, 30) }}</div>
                        <div class="text-xs text-gray-500">Created {{ $blast->created_at->diffForHumans() }}</div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm max-w-xs truncate">{{ Str::limit($blast->message, 50) }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <div>{{ $blast->total_recipients }}</div>
                        @if ($blast->status === 'sent' || $blast->status === 'failed')
                            <div class="text-xs text-gray-500">Sent: {{ $blast->sent_count }} | Failed: {{ $blast->failed_count }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @include('pages.admin-panel.sms-blast.partials.status-badge', ['blast' => $blast])
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($blast->sent_at)
                            {{ $blast->sent_at->format('M d, Y') }}<br>{{ $blast->sent_at->format('g:i A') }}
                        @elseif ($blast->scheduled_at)
                            {{ $blast->scheduled_at->format('M d, Y') }}<br>{{ $blast->scheduled_at->format('g:i A') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <div class="progress-bar w-20">
                                <div class="progress-fill @if($blast->success_rate >= 90) bg-green-500 @elseif($blast->success_rate >= 50) bg-yellow-500 @else bg-red-500 @endif"
                                    style="width: {{ min($blast->success_rate, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-medium">{{ $blast->success_rate }}%</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <a href="{{ route('sms_blast.show', $blast) }}" class="text-blue-600 hover:text-blue-900 mr-3">
                            <i class="fas fa-eye"></i>
                        </a>
                        @if ($blast->status === 'sent' || $blast->status === 'failed')
                            <button class="text-green-600 hover:text-green-900 mr-3"
                                onclick="resendFailed({{ $blast->id }})" title="Resend to failed">
                                <i class="fas fa-redo"></i>
                            </button>
                        @endif
                        @if ($blast->status === 'draft')
                            <a href="{{ route('sms_blast.edit', $blast) }}" class="text-yellow-600 hover:text-yellow-900 mr-3" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="{{ route('sms_blast.destroy', $blast) }}" class="inline" onsubmit="return confirm('Delete this blast?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-paper-plane text-gray-400 text-xl"></i>
                        </div>
                        <p class="text-sm">No SMS blasts yet</p>
                        <p class="text-xs text-gray-400 mt-1">Create your first blast to get started</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 flex items-center justify-between">
        <div class="text-sm font-semibold text-gray-800">
            Showing {{ $blasts->firstItem() ?? 0 }} to {{ $blasts->lastItem() ?? 0 }} of {{ $blasts->total() }} blasts
        </div>
        {{ $blasts->links() }}
    </div>
</div>

<script>
    function resendFailed(blastId) {
        if (!confirm('Resend to all failed recipients?')) return;

        window.axios.post(`/admin-panel/sms-blasts/${blastId}/resend`, {}, {
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            const data = response.data;
            console.log(data);

            if (data.success) {
                Swal.fire({
                    title: "Complete",
                    text: `Resend complete: ${data.sent} sent, ${data.failed} failed`,
                    icon: "success"
                });
                location.reload();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: (data.message || 'Unknown error'),
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: "error",
                title: "Network error",
                text: 'something went wrong',
            });
            console.error(error);
        });
    }
</script>
