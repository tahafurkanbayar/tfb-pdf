<?php

// Tool descriptions and texts shared across tool pages
return [
    'heading' => 'PDF tools',
    'descriptions' => [
        'merge' => 'Combine multiple PDFs into a single file in the order you choose.',
        'split' => 'Split a PDF into page ranges or individual pages.',
        'reorder' => 'Drag and drop to reorder pages or remove the ones you don\'t need.',
        'rotate' => 'Rotate selected pages by 90°, 180° or 270°.',
        'compress' => 'Reduce the file size of your PDF.',
        'watermark' => 'Add a text watermark to your pages.',
        'redact' => 'Permanently remove sensitive information from your document.',
        'ocr' => 'Turn scanned PDFs into searchable text.',
        'office' => 'Convert Word, Excel and PowerPoint files to PDF.',
        'sign' => 'Create a simple signature request for your document.',
    ],
    'unavailable_badge' => 'Not available on this server',
    'requires' => 'Required server component: :tool',
    'ui' => [
        'files_heading' => 'Files',
        'add_files' => 'Add files',
        'remove_file' => 'Remove :name from the list',
        'drag_handle' => 'Drag to change the order',
        'moved' => ':name moved to position :position.',
        'removed' => ':name removed from the list.',
        'upload_failed' => ':name could not be uploaded: :reason',
        'ready' => 'Ready',
        'list_note' => 'Files removed from the list remain in your documents.',
        'empty_list' => 'No files added yet.',
        'choose_document' => 'Upload a PDF, or open one of your documents and choose this tool.',
    ],
    'merge' => [
        'action' => 'Merge PDFs',
        'hint' => 'Put the files in the order you want. The merged file is saved as a new document.',
        'need_two' => 'Add at least two PDFs to merge.',
    ],
];
