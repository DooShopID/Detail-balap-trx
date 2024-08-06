<?php

if (!isset($_GET['token'])) {
    echo json_encode(array(
        "error" => "Token is required"
    ));
    exit;
}

$token = $_GET['token'];
$header = array("Authorization: Bearer ".$token);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$mh = curl_multi_init();
$ch_array = array();
$filteredResults = array();

$currentMonth = date('m');  // Mendapatkan bulan saat ini dalam format dua digit

for ($i = 1; $i <= 5; $i++) {
    $query = http_build_query(array("page" => $i));
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://bukaolshop.net/api/v1/transaksi/list?" . $query);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    curl_multi_add_handle($mh, $ch);
    $ch_array[] = $ch;
}

$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

foreach ($ch_array as $ch) {
    $response = json_decode(curl_multi_getcontent($ch), true);
    // Filter data untuk bulan yang sesuai dengan bulan saat ini dan status_pengiriman diterima atau selesai
    if ($response['code'] == 200 && $response['status'] == 'ok') {
        foreach ($response['data'] as $transaction) {
            $transactionMonth = date('m', strtotime($transaction['tanggal']));
            if ($transactionMonth == $currentMonth && 
                ($transaction['status_pengiriman'] == 'diterima' || $transaction['status_pengiriman'] == 'selesai')) {
                $filteredResults[] = $transaction;
            }
        }
    }
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}

curl_multi_close($mh);

// Mengelompokkan transaksi berdasarkan id_user dan menghitung jumlah transaksi per user
$userTransactions = array();
foreach ($filteredResults as $transaction) {
    $id_user = $transaction['id_user'];
    if (!isset($userTransactions[$id_user])) {
        $userTransactions[$id_user] = array(
            "id_user" => $id_user,
            "nama_user" => null,
            "email_user" => null,
            "nomor_telepon" => null,
            "foto_profil" => null,
            "total_transaksi" => 0
        );
    }
    $userTransactions[$id_user]["total_transaksi"]++;
}

// Proses untuk mengambil detail user berdasarkan id_user dan melengkapi informasi user
foreach ($userTransactions as &$userTransaction) {
    $id_user = $userTransaction["id_user"];
    $query = http_build_query(array("id_user" => $id_user));
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://bukaolshop.net/api/v1/member/id?" . $query);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $userResponse = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if ($userResponse['code'] == 200 && $userResponse['status'] == 'ok') {
        $userTransaction["nama_user"] = maskUserName($userResponse["nama_user"]);
        $userTransaction["email_user"] = maskEmail($userResponse["email_user"]);
        $userTransaction["nomor_telepon"] = maskPhoneNumber($userResponse["nomor_telepon"]);
        $userTransaction["foto_profil"] = $userResponse["foto_profil"] ?? "https://apiku.site/transaksi/img/default_nodata.png";
    } else {
        $userTransaction["foto_profil"] = "https://apiku.site/transaksi/img/default_nodata.png";
    }
}

// Fungsi untuk menyensor nama user
function maskUserName($name) {
    if (strlen($name) > 4) {
        return substr($name, 0, -4) . '****';
    } else {
        return substr($name, 0, 1) . '****';
    }
}

// Fungsi untuk menyensor email
function maskEmail($email) {
    $atPos = strpos($email, '@');
    if ($atPos !== false) {
        $emailPrefix = substr($email, 0, $atPos);
        $emailDomain = substr($email, $atPos);
        if (strlen($emailPrefix) > 4) {
            return substr($emailPrefix, 0, -4) . '****' . $emailDomain;
        } else {
            return '****' . $emailDomain;
        }
    }
    return $email;
}

// Fungsi untuk menyensor nomor telepon
function maskPhoneNumber($phoneNumber) {
    if (strlen($phoneNumber) > 4) {
        return substr($phoneNumber, 0, -4) . '****';
    } else {
        return '****';
    }
}

// Mengurutkan user berdasarkan total_transaksi terbanyak
usort($userTransactions, function($a, $b) {
    return $b["total_transaksi"] - $a["total_transaksi"];
});

// Mengambil 10 user teratas
$userTransactions = array_slice($userTransactions, 0, 10);

// Menambahkan urutan_transaksi
foreach ($userTransactions as $index => &$userTransaction) {
    $userTransaction["urutan_transaksi"] = $index + 1;
}

// Output final dalam format yang diminta
echo json_encode(array(
    "creator" => "roadpedia",
    "data" => $userTransactions
));

?>
