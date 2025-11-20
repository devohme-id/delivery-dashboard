<?php
// File: public/proxy.php
// Fungsi: Mengambil gambar dari server HTTP internal agar bisa ditampilkan di website HTTPS

if (isset($_GET['url'])) {
    $imageUrl = $_GET['url'];

    // VALIDASI KEAMANAN:
    // Hanya izinkan URL yang berasal dari server internal yang kita kenal
    // Ganti '10.217.4.115' sesuai dengan IP server gambar Anda jika berubah
    $allowedHost = '10.217.4.115';

    // Parse URL untuk cek host
    $parsedUrl = parse_url($imageUrl);

    if (isset($parsedUrl['host']) && $parsedUrl['host'] === $allowedHost) {

        // Ambil informasi gambar (MIME type)
        $imgInfo = @getimagesize($imageUrl);

        if ($imgInfo) {
            // Teruskan header content-type yang sesuai (misal: image/jpeg)
            header("Content-type: " . $imgInfo['mime']);

            // Outputkan isi file gambar
            readfile($imageUrl);
        } else {
            // Jika gambar tidak ditemukan atau error
            http_response_code(404);
        }
    } else {
        // Jika URL tidak diizinkan
        http_response_code(403);
        echo "Access Denied: External proxy not allowed.";
    }
}
?>