<?php
// pages/admin/excel_import.php
?>
<div class="p-6 bg-white rounded-lg shadow-md">
    <div class="flex items-center mb-6">
        <a href="adminhome.php" class="mr-4 text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <h2 class="text-2xl font-bold text-gray-800">Import Masterlist</h2>
    </div>

    <div class="border-2 border-dashed border-gray-300 p-8 text-center rounded-lg">
        <form action="adminhome.php?p=excel_import&action=upload" method="POST" enctype="multipart/form-data">
            <i class="fas fa-file-excel text-5xl text-green-600 mb-4"></i>
            <p class="mb-4 text-gray-600">Upload the institutional Excel file (.xlsx or .csv)</p>
            <input type="file" name="masterlist_file" accept=".xlsx, .xls, .csv" class="mb-4 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded shadow hover:bg-blue-700 transition">
                Process and Seed Database
            </button>
        </form>
    </div>
</div>