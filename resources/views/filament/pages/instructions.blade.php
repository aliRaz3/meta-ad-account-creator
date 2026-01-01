<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Telegram Bot Setup --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-6 w-6"/>
                    <span>Telegram Bot Setup</span>
                </div>
            </x-slot>

            <x-slot name="description">
                Learn how to create a Telegram bot and get your Chat ID for notifications.
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <h3 class="text-lg font-semibold mb-3">Step 1: Create a Telegram Bot</h3>
                <ol class="list-decimal list-inside space-y-2">
                    <li>Open Telegram and search for <code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded">@BotFather</code></li>
                    <li>Send the command <code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded">/newbot</code></li>
                    <li>Follow the prompts to choose a name and username for your bot</li>
                    <li>BotFather will send you a <strong>Bot Token</strong> - save this!</li>
                    <li>Example token: <code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">123456789:ABCdefGHIjklMNOpqrsTUVwxyz</code></li>
                </ol>

                <h3 class="text-lg font-semibold mt-6 mb-3">Step 2: Get Your Chat ID</h3>
                <ol class="list-decimal list-inside space-y-2">
                    <li>Search for your newly created bot in Telegram</li>
                    <li>Send your bot a message (anything, like "Hello")</li>
                    <li>Now search for <code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded">@userinfobot</code></li>
                    <li>Send <code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded">/start</code> to @userinfobot</li>
                    <li>It will reply with your <strong>Chat ID</strong> (a number like 123456789)</li>
                </ol>

                <h3 class="text-lg font-semibold mt-6 mb-3">Step 3: Add Bot to AdAccount Generator</h3>
                <ol class="list-decimal list-inside space-y-2">
                    <li>Go to the <strong>Telegram Bots</strong> page</li>
                    <li>Click <strong>Create</strong></li>
                    <li>Enter your Bot Token and Chat ID</li>
                    <li>Select which events you want to be notified about</li>
                    <li>Save and test your bot with the <strong>Test</strong> button</li>
                </ol>

                <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        <strong>💡 Tip:</strong> You can add multiple bots with different notification preferences.
                        For example, one bot for critical errors and another for progress updates.
                    </p>
                </div>
            </div>
        </x-filament::section>

        {{-- Proxy Setup --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-globe-alt" class="h-6 w-6"/>
                    <span>Proxy Configuration</span>
                </div>
            </x-slot>

            <x-slot name="description">
                Configure proxies to use for Meta API requests.
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <h3 class="text-lg font-semibold mb-3">Supported Proxy Formats</h3>

                <div class="space-y-3">
                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <p class="font-semibold mb-2">HTTP Proxy:</p>
                        <code class="block px-3 py-2 bg-gray-100 dark:bg-gray-900 rounded text-sm">
                            http://proxy.example.com:8080<br>
                            http://username:password@proxy.example.com:8080
                        </code>
                    </div>

                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <p class="font-semibold mb-2">HTTPS Proxy:</p>
                        <code class="block px-3 py-2 bg-gray-100 dark:bg-gray-900 rounded text-sm">
                            https://proxy.example.com:443<br>
                            https://username:password@proxy.example.com:443
                        </code>
                    </div>

                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <p class="font-semibold mb-2">SOCKS4 Proxy:</p>
                        <code class="block px-3 py-2 bg-gray-100 dark:bg-gray-900 rounded text-sm">
                            socks4://proxy.example.com:1080
                        </code>
                    </div>

                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <p class="font-semibold mb-2">SOCKS5 Proxy:</p>
                        <code class="block px-3 py-2 bg-gray-100 dark:bg-gray-900 rounded text-sm">
                            socks5://proxy.example.com:1080<br>
                            socks5://username:password@proxy.example.com:1080
                        </code>
                    </div>
                </div>

                <h3 class="text-lg font-semibold mt-6 mb-3">Bulk Import</h3>
                <p>You can import multiple proxies at once using the <strong>Bulk Import</strong> button. Simply paste your proxy URLs, one per line:</p>

                <code class="block p-3 bg-gray-100 dark:bg-gray-900 rounded text-sm mt-2">
                    http://user1:pass1@proxy1.com:8080<br>
                    https://proxy2.com:443<br>
                    socks5://user2:pass2@proxy3.com:1080<br>
                    http://proxy4.com:8888
                </code>

                <h3 class="text-lg font-semibold mt-6 mb-3">Proxy Rotation Types</h3>
                <dl class="space-y-3">
                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <dt class="font-semibold">Round Robin</dt>
                        <dd class="text-sm mt-1">Cycles through proxies sequentially. Each request uses the next proxy in the list.</dd>
                    </div>
                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <dt class="font-semibold">Random</dt>
                        <dd class="text-sm mt-1">Randomly selects a proxy for each request.</dd>
                    </div>
                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <dt class="font-semibold">Sequential</dt>
                        <dd class="text-sm mt-1">Uses the least recently used proxy.</dd>
                    </div>
                </dl>

                <h3 class="text-lg font-semibold mt-6 mb-3">Proxy Validation</h3>
                <p>All proxies should be validated before use. The system will:</p>
                <ul class="list-disc list-inside space-y-1 mt-2">
                    <li>Test connectivity by making a request through the proxy</li>
                    <li>Track success and failure counts</li>
                    <li>Automatically disable proxies with high failure rates (≥10 failures with &lt;5 successes)</li>
                </ul>

                <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        <strong>⚠️ Important:</strong> Enable proxies in <strong>Settings</strong> and ensure at least one validated proxy is active before creating BM Jobs.
                    </p>
                </div>
            </div>
        </x-filament::section>

        {{-- Notification Events --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-bell" class="h-6 w-6"/>
                    <span>Notification Events</span>
                </div>
            </x-slot>

            <x-slot name="description">
                Available notification events for BM Jobs.
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <h4 class="font-semibold flex items-center gap-2">
                            🚀 Job Started
                        </h4>
                        <p class="text-sm mt-1">When a BM job begins processing ad accounts.</p>
                    </div>

                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <h4 class="font-semibold flex items-center gap-2">
                            ✅ Job Completed
                        </h4>
                        <p class="text-sm mt-1">When a BM job successfully completes all accounts.</p>
                    </div>

                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <h4 class="font-semibold flex items-center gap-2">
                            ❌ Job Failed
                        </h4>
                        <p class="text-sm mt-1">When a BM job encounters a fatal error.</p>
                    </div>

                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <h4 class="font-semibold flex items-center gap-2">
                            ⏸️ Job Paused
                        </h4>
                        <p class="text-sm mt-1">When you manually pause a running job.</p>
                    </div>

                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <h4 class="font-semibold flex items-center gap-2">
                            ▶️ Job Resumed
                        </h4>
                        <p class="text-sm mt-1">When a paused job resumes processing.</p>
                    </div>

                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <h4 class="font-semibold flex items-center gap-2">
                            📊 Progress Milestones
                        </h4>
                        <p class="text-sm mt-1">At 25%, 50%, and 75% completion of accounts created.</p>
                    </div>

                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <h4 class="font-semibold flex items-center gap-2">
                            ⚠️ System Errors
                        </h4>
                        <p class="text-sm mt-1">Critical system-level errors that require attention.</p>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
