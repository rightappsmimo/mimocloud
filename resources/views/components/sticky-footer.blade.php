<footer class="sm:fixed relative bottom-0 group w-full px-5 py-1 bg-[var(--color-primary)] backdrop-blur-xl opacity-60 z-80">
    <section class="flex flex-col sm:flex-row justify-between">
        <div class="flex flex-col sm:flex-row sm:gap-8 text-xs sm:text-sm">
            <p class="font-bold">Powered by Right Apps Incorporated</p>
            <p class="italic font-semibold">Server IP: {{ env('DB_HOST') .':'.  env('DB_PORT') .'/'. env('DB_DATABASE')}}</p>
            <p class="italic font-semibold">
                Logged In On:
                {{ session('logged_in_at')
                    ? session('logged_in_at')->format('F d, Y h:i A')
                    : 'undefined'
                }}
            </p>
        </div>
        <div class="flex flex-row gap-8 text-xs sm:text-sm">
            <p>Cloud Mimo Web App</p>
        </div>
    </section>
</footer>
