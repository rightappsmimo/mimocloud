@php
    $selectClass = 'border border-[var(--color-primary)] px-4 py-2 focus:ring-[var(--color-primary)] rounded-md shadow transition-all duration-300';

    // Pre-fill values from existing blast
    $existingTitle = old('title', $smsBlast->title);
    $existingType = old('type', $smsBlast->type);
    $existingMessage = old('message', $smsBlast->message);
    $existingSendMode = old('send_mode', $smsBlast->send_mode);
    $existingSlug = old('slug', $smsBlast->slug);
    $existingScheduledDate = old('scheduled_date', $smsBlast->scheduled_at ? $smsBlast->scheduled_at->format('Y-m-d') : '');
    $existingScheduledTime = old('scheduled_time', $smsBlast->scheduled_at ? $smsBlast->scheduled_at->format('H:i') : '');

    // Build selected recipients lookup
    $selectedIdsArray = $selectedIds ?? [];
@endphp

<div class="rounded-lg bg-gradient-to-r from-[var(--color-accent-mid-dark)] to-[var(--color-accent)] p-6 lg:p-8 flex flex-col gap-4">
    <div class="flex flex-col gap-4">
        <a href="{{ route('sms_blast.index') }}" class="max-w-52 px-4 text-[var(--color-accent)] py-2 bg-gray-500 rounded-lg hover:opacity-80 transition-all duration-300">
            <i class="fas fa-arrow-left"></i> Back to SMS Blasts
        </a>
        <h1 class="text-3xl font-bold text-[var(--color-primary-full-dark)]">Edit SMS Blast #{{ $smsBlast->id }}</h1>
        <p class="text-gray-100 dark:text-gray-600 text-sm">Update and resend bulk SMS to parents and guardians</p>
    </div>

    <form action="{{ route('sms_blast.update', $smsBlast) }}" method="POST" class="mx-auto flex flex-col w-full shadow-md space-y-4 rounded-lg border border-white bg-white/60 backdrop-blur-xl p-6">
        @csrf
        @method('PUT')
        <div class="flex flex-wrap gap-4">
            <div class="flex-1">
                <x-input-label for="title" value="Title" />
                <x-text-input id="title" name="title" class="mt-1 w-full" value="{{ $existingTitle }}" />
            </div>
            <div class="flex-1">
                <x-input-label for="type" class="mb-1" value="Type" />
                <select name="type" id="type" class="{{ $selectClass }}" required>
                    <option value="">-- Blast Type --</option>
                    <option value="automation" {{ $existingType === 'automation' ? 'selected' : '' }}>Automation</option>
                    <option value="campaign" {{ $existingType === 'campaign' ? 'selected' : '' }}>Campaign</option>
                </select>
            </div>
            <input type="hidden" id="hidden-slug" name="slug" value="{{ $existingSlug }}">
        </div>

        <div class="flex flex-col p-3 rounded-lg border border-white backdrop-blur bg-[var(--color-primary)]/10 shadow-md">
            <x-input-label for="message" value="Message Content" />
            <textarea
                class="resize-none font-mono text-sm text-gray-400 focus:text-gray-900 border border-[var(--color-primary)] px-4 py-2 focus:ring-[var(--color-primary)] rounded-md shadow transition-all duration-300"
                id="messageInput"
                name="message"
                rows="4"
                maxlength="255"
                placeholder="Type your message here..."
                required
            >{{ $existingMessage }}</textarea>
            <p class="text-xs text-gray-600 mt-1">
                <span id="charCount">{{ strlen($existingMessage) }}</span> / 255 characters
            </p>
            <div class="mt-4">
                <x-input-label value="Quick Templates" class="mb-1" />
                <select id="templateSelect" class="{{ $selectClass }}">
                    <option value="">Select a template...</option>
                    @foreach($templates as $index => $template)
                    <option value="{{ $index }}">{{ $template['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <p class="text-xs font-semibold text-gray-600 mt-2 mb-2">Available variables:</p>
            <div class="flex flex-wrap gap-2">
                <span class="px-2 py-1 bg-blue-700 text-gray-100 text-xs rounded">{child_name}</span>
                <span class="px-2 py-1 bg-green-700 text-gray-100 text-xs rounded">{parent_name}</span>
                <span class="px-2 py-1 bg-amber-700 text-gray-100 text-xs rounded">{time_remaining}</span>
                <span class="px-2 py-1 bg-red-700 text-gray-100 text-xs rounded">{minutes_over}</span>
                <span class="px-2 py-1 bg-purple-700 text-gray-100 text-xs rounded">{checkout_time}</span>
            </div>
        </div>

        <div class="flex flex-row gap-4">
            <div>
                <x-input-label for="send_mode" value="Send Mode" />
                <select name="send_mode" id="send-mode" class="{{ $selectClass }}" required>
                    <option value="">-- Schedule Send --</option>
                    <option value="now" {{ $existingSendMode === 'now' ? 'selected' : '' }}>Now</option>
                    <option value="scheduled" {{ $existingSendMode === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                    <option value="alltimes" {{ $existingSendMode === 'alltimes' ? 'selected' : '' }}>Every Time</option>
                </select>
            </div>
            <div id="scheduleFields" class="{{ $existingSendMode === 'scheduled' ? '' : 'hidden' }}">
                <div class="flex flex-row gap-2 space-y-3">
                    <div>
                        <x-input-label for="scheduled_date" value="Schedule Date" />
                        <x-text-input type="date" id="schedule-date" name="scheduled_date" value="{{ $existingScheduledDate }}" />
                    </div>
                    <div>
                        <x-input-label for="scheduled_time" value="Schedule Time" />
                        <x-text-input type="time" id="schedule-time" name="scheduled_time" value="{{ $existingScheduledTime }}" />
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-4">
            <div class="flex-2">
                <x-input-label value="Recipients" />
                <select id="recipientMode" class="{{ $selectClass }}">
                    <option value="all">All Contacts</option>
                    <option value="search" selected>Search & Select</option>
                </select>
            </div>

            <div id="searchBox" class="flex-1 mt-3 space-y-2">
                <input
                    type="text"
                    id="contactSearch"
                    class="w-full border rounded-lg px-4 py-2"
                    placeholder="Search name or number..."
                />
                <div id="searchResults" class="border rounded-lg p-2 max-h-60 overflow-y-auto space-y-2"></div>
                <div class="mt-2">
                    <p class="text-xs text-gray-500">Selected:</p>
                    <div id="selectedList" class="flex flex-wrap gap-2"></div>
                </div>
                <div id="hiddenRecipients"></div>
            </div>
        </div>

        <button class="block w-full rounded-lg border border-[var(--color-primary)] bg-[var(--color-primary)] px-12 py-3 text-sm font-medium text-white transition-opacity hover:opacity-75" type="submit">
            Update Blast
        </button>
    </form>

</div>

<script>
    window.adminPanelStates = {
        templates: @json($templates),
        maxChars: 255,
    };

    // Pre-selected recipient IDs from the existing blast
    window.preSelectedIds = @json($selectedIdsArray);

    // All recipients fetched from the server for searching
    const allRecipients = @json($parents->merge($guardians));

    // Selected recipients Set (initialized from pre-selected IDs)
    let selectedIds = new Set(window.preSelectedIds || []);

    // Character count
    function updateCharCount() {
        const input = document.getElementById('messageInput');
        const countEl = document.getElementById('charCount');
        if (input && countEl) {
            countEl.textContent = input.value.length;
        }
    }

    // Template selection
    document.getElementById('templateSelect').addEventListener('change', function() {
        const templates = window.adminPanelStates.templates;
        const index = this.value;
        if (index !== '' && templates[index]) {
            document.querySelector('input[name="title"]').value = templates[index].name;
            document.getElementById('messageInput').value = templates[index].message;
            updateCharCount();
        }
    });

    document.getElementById('messageInput').addEventListener('input', updateCharCount);

    // Schedule toggle
    document.getElementById('send-mode').addEventListener('change', function() {
        const input = document.getElementById('scheduleFields');
        if (this.value === 'scheduled') {
            input.classList.remove('hidden');
        } else {
            input.classList.add('hidden');
        }
    });

    // Recipient mode toggle
    document.getElementById('recipientMode').addEventListener('change', function() {
        const searchBox = document.getElementById('searchBox');
        if (this.value === 'search') {
            searchBox.classList.remove('hidden');
        } else {
            searchBox.classList.add('hidden');
        }
    });

    // Search contacts
    let searchTimeout;
    document.getElementById('contactSearch').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const query = e.target.value.toLowerCase().trim();
            const resultsContainer = document.getElementById('searchResults');

            if (query === '') {
                resultsContainer.innerHTML = '';
                return;
            }

            const filtered = allRecipients.filter(r => {
                const name = (r.d_name || r.name || '').toLowerCase();
                const mobile = (r.mobileno || r.mobile || '').toLowerCase();
                return name.includes(query) || mobile.includes(query);
            });

            resultsContainer.innerHTML = '';

            if (filtered.length === 0) {
                resultsContainer.innerHTML = '<p class="text-sm text-gray-500 p-2">No contacts found</p>';
                return;
            }

            filtered.forEach(recipient => {
                const id = recipient.d_code || recipient.id;
                const name = recipient.d_name || recipient.name || '';
                const mobile = recipient.mobileno || recipient.mobile || '';
                const isSelected = selectedIds.has(id);
                const type = recipient.isparent ? 'parent' : (recipient.isguardian ? 'guardian' : 'other');
                const typeLabel = type === 'parent' ? 'Parent' : (type === 'guardian' ? 'Guardian' : 'Other');
                const bgClass = type === 'parent' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700' : (type === 'guardian' ? 'bg-purple-100 dark:bg-purple-900 text-purple-700' : 'bg-gray-100 text-gray-700');

                const div = document.createElement('div');
                div.className = 'flex items-center justify-between p-2 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors' + (isSelected ? ' bg-blue-50 dark:bg-blue-900/20' : '');
                div.setAttribute('data-id', id);
                div.innerHTML = `
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ${isSelected ? 'bg-blue-600 text-white' : bgClass}">
                            ${name.charAt(0).toUpperCase()}
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-medium truncate">${name}</div>
                            <div class="text-xs text-gray-500 truncate">${mobile}</div>
                        </div>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full ${bgClass}">${typeLabel}</span>
                `;

                div.addEventListener('click', () => {
                    if (selectedIds.has(id)) {
                        selectedIds.delete(id);
                        div.classList.remove('bg-blue-50', 'dark:bg-blue-900/20');
                    } else {
                        selectedIds.add(id);
                        div.classList.add('bg-blue-50', 'dark:bg-blue-900/20');
                    }
                    refreshSelectedList();
                });

                resultsContainer.appendChild(div);
            });
        }, 200);
    });

    // Refresh selected recipients list
    function refreshSelectedList() {
        const container = document.getElementById('selectedList');
        const hiddenContainer = document.getElementById('hiddenRecipients');
        if (!container) return;

        container.innerHTML = '';
        hiddenContainer.innerHTML = '';

        selectedIds.forEach(id => {
            const recipient = allRecipients.find(r => (r.d_code || r.id) === id);
            if (!recipient) return;

            const name = recipient.d_name || recipient.name || '';
            const mobile = recipient.mobileno || recipient.mobile || '';
            const type = recipient.isparent ? 'parent' : (recipient.isguardian ? 'guardian' : 'other');
            const bgClass = type === 'parent' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700' : (type === 'guardian' ? 'bg-purple-100 dark:bg-purple-900 text-purple-700' : 'bg-gray-100 text-gray-700');

            // Hidden inputs for form submission
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'recipient_ids[]';
            hiddenInput.value = id;
            hiddenContainer.appendChild(hiddenInput);

            const div = document.createElement('div');
            div.className = 'flex items-center gap-1 bg-gray-50 dark:bg-[#0a0a0a] rounded-lg px-2 py-1';
            div.setAttribute('data-id', id);
            div.innerHTML = `
                <div class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold ${bgClass}">${name.charAt(0).toUpperCase()}</div>
                <span class="text-xs font-medium truncate max-w-[100px]">${name}</span>
                <button type="button" class="text-gray-400 hover:text-red-500 ml-1" onclick="removeRecipient('${id}')">
                    <i class="fas fa-times text-[10px]"></i>
                </button>
            `;
            container.appendChild(div);
        });
    }

    function removeRecipient(id) {
        selectedIds.delete(id);
        // Remove from search results highlight
        const card = document.querySelector(`.cursor-pointer[data-id="${id}"]`);
        if (card) {
            card.classList.remove('bg-blue-50', 'dark:bg-blue-900/20');
        }
        refreshSelectedList();
    }

    // Initialize pre-selected recipients on load
    function initPreSelected() {
        const preSelected = window.preSelectedIds || [];
        if (preSelected.length > 0) {
            preSelected.forEach(id => {
                selectedIds.add(id);
                // Add hidden inputs
                const hiddenContainer = document.getElementById('hiddenRecipients');
                if (hiddenContainer) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'recipient_ids[]';
                    hiddenInput.value = id;
                    hiddenContainer.appendChild(hiddenInput);
                }
            });
            refreshSelectedList();
        }
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'a' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'SELECT') {
            e.preventDefault();
            const results = document.querySelectorAll('#searchResults > div[data-id]');
            results.forEach(div => {
                const id = div.getAttribute('data-id');
                if (id) {
                    selectedIds.add(id);
                    div.classList.add('bg-blue-50', 'dark:bg-blue-900/20');
                }
            });
            refreshSelectedList();

            // Toast
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity';
            toast.textContent = `Selected all ${results.length} recipients`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        updateCharCount();
        initPreSelected();

        // If send mode was 'scheduled', ensure fields are visible
        const sendMode = document.getElementById('send-mode');
        if (sendMode && sendMode.value === 'scheduled') {
            document.getElementById('scheduleFields').classList.remove('hidden');
        }
    });
</script>