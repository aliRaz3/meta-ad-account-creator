<div class="text-sm space-y-3">
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
        <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">📋 Excel File Format</h4>
        <p class="text-blue-800 dark:text-blue-200 mb-2">Your Excel file must contain the following columns:</p>
        <ul class="list-disc list-inside text-blue-700 dark:text-blue-300 space-y-1 ml-2">
            <li><strong>title</strong> - A friendly name for the BM account (required)</li>
            <li><strong>business_portfolio_id</strong> - Meta Business Portfolio ID (required, must be unique)</li>
            <li><strong>access_token</strong> - Meta API access token (required)</li>
            <li><strong>new_bm_name</strong> - New business name to update via API (optional)</li>
        </ul>
    </div>

    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-4">
        <h4 class="font-semibold text-green-900 dark:text-green-100 mb-2">✅ Tips for Success</h4>
        <ul class="list-disc list-inside text-green-800 dark:text-green-200 space-y-1 ml-2">
            <li>First row must be the header row with column names</li>
            <li>Column names are case-insensitive (e.g., "Title" or "title" both work)</li>
            <li>Empty rows will be automatically skipped</li>
            <li>Maximum file size: 5MB</li>
            <li>Supported format: .xlsx (modern Excel format)</li>
        </ul>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg p-3">
        <p class="text-gray-700 dark:text-gray-300 text-xs">
            <strong>Example:</strong> Download a sample template or format your Excel file like this:
        </p>
        <div class="mt-2 overflow-x-auto">
            <table class="min-w-full text-xs border-collapse">
                <thead class="bg-gray-100 dark:bg-gray-700">
                    <tr>
                        <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left">title</th>
                        <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left">business_portfolio_id</th>
                        <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left">access_token</th>
                        <th class="border border-gray-300 dark:border-gray-600 px-2 py-1 text-left">new_bm_name</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900">
                    <tr>
                        <td class="border border-gray-300 dark:border-gray-600 px-2 py-1">My BM Account</td>
                        <td class="border border-gray-300 dark:border-gray-600 px-2 py-1">123456789</td>
                        <td class="border border-gray-300 dark:border-gray-600 px-2 py-1">EAABsz...</td>
                        <td class="border border-gray-300 dark:border-gray-600 px-2 py-1">Updated Name</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
