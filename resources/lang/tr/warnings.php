<?php

// PDF değişiklik uyarıları (spec §36)
return [
    'signature_invalidated' => 'Bu belgede dijital imza var. Belgeyi değiştiren her işlem mevcut dijital imzaları geçersiz kılar; yeni dosyada imza doğrulanamaz.',
    'forms_removed' => 'Form alanları yeni dosyaya doldurulabilir alan olarak aktarılmaz; yalnızca görünümleri korunur.',
    'embedded_files_removed' => 'Belgeye gömülü ek dosyalar yeni dosyaya aktarılmaz.',
    'bookmarks_removed' => 'Yer imleri (içindekiler) yeni dosyaya aktarılmaz.',
    'javascript_removed' => 'Belgedeki JavaScript eylemleri yeni dosyaya aktarılmaz.',
    'tags_removed' => 'Erişilebilirlik etiketleri (tagged PDF yapısı) yeni dosyaya aktarılmaz.',
    'metadata_changed' => 'Belge bilgileri (başlık, yazar vb. metadata) yeni dosyada değişebilir.',
    'links_removed' => 'Sayfaların görünümü korunur; ancak tıklanabilir bağlantılar ve notlar (açıklamalar) yeni dosyaya aktarılmaz.',
    'encryption_changed' => 'Kaynak dosyadaki şifreleme ve izin ayarları yeni dosyaya aktarılmaz.',
    'font_substitution' => 'Sunucuda bulunmayan yazı tipleri benzerleriyle değiştirilebilir; metnin görünümü farklı olabilir.',
    'layout_changes' => 'Sayfa düzeni, satır ve sayfa sonları özgün belgeden farklı olabilir.',
    'office_forms' => 'Office belgesindeki form denetimleri PDF\'te doldurulabilir alan olarak kalmayabilir.',
    'embedded_objects' => 'Gömülü nesneler (grafikler, OLE nesneleri, videolar) PDF\'e statik görüntü olarak aktarılabilir veya hiç aktarılmayabilir.',
    'rasterized_pages' => 'Karartılan sayfalar görüntüye dönüştürülür: bu sayfalardaki metin artık seçilemez ve aranamaz.',
    'ocr_accuracy' => 'OCR sonucu %100 doğru olmayabilir. Önemli bilgileri özgün belgeyle karşılaştırın.',
    'images_recompressed' => 'Görüntüler daha düşük çözünürlük ve kaliteyle yeniden kaydedildi; yakınlaştırıldığında veya basıldığında fark edilebilir.',
];
