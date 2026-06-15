<?php
/**
 * Eczane Otomasyon Sistemi
 * 
 * Özellikler:
 * - PHP OOP & MySQLi Object Oriented
 * - Abstract Class (BaseManager) & Inheritance (Auth/Product/Customer/Sales Managers)
 * - Personel Giriş Sistemi (Varsayılan: admin | 123456)
 * - İlaç & Müşteri CRUD
 * - Sepet & Satış İşlemleri (İşlemsel Stok Düşme - Transactions)
 * - Raporlama & Kritik Stok Takibi
 * - Modern Kırmızı Temalı Arayüz (Inter Font, Glassmorphism, Dynamic CSS)
 * - Canlı Arama/Filtreleme ve Modallar (Pure JS)
 */

// 1. Session Başlatma
session_start();

// 2. Veritabanı Sabitleri
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'eczane_otomasyonu');

// 3. Veritabanı Otomatik Kurulum ve Bağlantı (MySQLi Object Oriented)
try {
    // Önce sunucuya bağlan
    $mysql = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($mysql->connect_error) {
        throw new Exception("MySQL sunucusuna bağlanılamadı: " . $mysql->connect_error);
    }
    
    // Veritabanını oluştur
    $mysql->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    
    // Veritabanını seç
    if (!$mysql->select_db(DB_NAME)) {
        throw new Exception("Veritabanı seçilemedi: " . $mysql->error);
    }
    
    // UTF-8 Karakter seti ayarı
    $mysql->set_charset("utf8mb4");
    
    // Tabloları oluştur
    $mysql->query("CREATE TABLE IF NOT EXISTS `personeller` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `kullanici_adi` VARCHAR(50) NOT NULL UNIQUE,
      `sifre` VARCHAR(255) NOT NULL,
      `ad_soyad` VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $mysql->query("CREATE TABLE IF NOT EXISTS `urunler` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `urun_adi` VARCHAR(100) NOT NULL,
      `stok` INT NOT NULL,
      `fiyat` DECIMAL(10,2) NOT NULL,
      `barkod` VARCHAR(50) UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $mysql->query("CREATE TABLE IF NOT EXISTS `musteriler` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `ad_soyad` VARCHAR(100) NOT NULL,
      `telefon` VARCHAR(20) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // TC Kimlik ve Adres alanları yoksa ekle (mevcut kurulumlar için)
    $mysql->query("ALTER TABLE `musteriler` ADD COLUMN IF NOT EXISTS `tc_kimlik` VARCHAR(11) DEFAULT NULL");
    $mysql->query("ALTER TABLE `musteriler` ADD COLUMN IF NOT EXISTS `adres` TEXT DEFAULT NULL");

    $mysql->query("CREATE TABLE IF NOT EXISTS `satislar` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `urun_id` INT DEFAULT NULL,
      `musteri_id` INT DEFAULT NULL,
      `adet` INT NOT NULL,
      `toplam_tutar` DECIMAL(10,2) NOT NULL,
      `tarih` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`) ON DELETE SET NULL,
      FOREIGN KEY (`musteri_id`) REFERENCES `musteriler` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Varsayılan Başlangıç Verilerini Ekle
    // Personel (admin | 123456) — şifre dinamik olarak hash'lenir
    $checkUser = $mysql->query("SELECT COUNT(*) as total FROM `personeller`");
    $userCount = $checkUser->fetch_assoc();
    if ($userCount['total'] == 0) {
        $hashedPass = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $mysql->prepare("INSERT INTO `personeller` (`kullanici_adi`, `sifre`, `ad_soyad`) VALUES ('admin', ?, 'Ecz. Ahmet Yılmaz')");
        $stmt->bind_param("s", $hashedPass);
        $stmt->execute();
        $stmt->close();
    }
    
    // Ürünler (İlaçlar)
    $checkUrun = $mysql->query("SELECT COUNT(*) as total FROM `urunler`");
    $urunCount = $checkUrun->fetch_assoc();
    if ($urunCount['total'] == 0) {
        $mysql->query("INSERT INTO `urunler` (`urun_adi`, `stok`, `fiyat`, `barkod`) VALUES
        ('Parol 500 mg Tablet', 150, 48.50, '8699525010017'),
        ('Arveles 25 mg Film Kaplı', 85, 64.20, '8699546090012'),
        ('Augmentin BID 1000 mg', 40, 118.00, '8699540090025'),
        ('Majezik Oral Sprey', 25, 92.10, '8699508510039')");
    }
    
    // Müşteriler
    $checkMusteri = $mysql->query("SELECT COUNT(*) as total FROM `musteriler`");
    $musteriCount = $checkMusteri->fetch_assoc();
    if ($musteriCount['total'] == 0) {
        $mysql->query("INSERT INTO `musteriler` (`ad_soyad`, `telefon`) VALUES
        ('Mehmet Can', '0555 111 22 33'),
        ('Selin Demir', '0532 222 33 44')");
    }
    
} catch (Exception $e) {
    die("Veritabanı kurulum hatası: " . $e->getMessage() . "<br><small>Lütfen local MySQL (XAMPP) servisinizin çalıştığından emin olun.</small>");
}

// 4. OOP Sınıflarının Tanımlanması
/**
 * Abstract Class: BaseManager
 * Tüm veri yöneticileri için temel taban sınıf.
 */
abstract class BaseManager {
    protected $db;
    
    public function __construct(mysqli $db) {
        $this->db = $db;
    }
    
    // Alt sınıflar kendi tablo isimlerini dönmek zorundadır (Abstraction)
    abstract public function getTableName(): string;
    
    // ID ile veri bulma (Inherited Method)
    public function findById(int $id): ?array {
        $table = $this->getTableName();
        $stmt = $this->db->prepare("SELECT * FROM `$table` WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result->fetch_assoc();
    }
    
    // Tablodaki tüm verileri listeleme (Inherited Method)
    public function getAll(string $orderBy = 'id DESC'): array {
        $table = $this->getTableName();
        $result = $this->db->query("SELECT * FROM `$table` ORDER BY $orderBy");
        $list = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $list[] = $row;
            }
        }
        return $list;
    }
    
    // Tablodaki toplam kayıt sayısını alma (Inherited Method)
    public function getCount(): int {
        $table = $this->getTableName();
        $result = $this->db->query("SELECT COUNT(*) as cnt FROM `$table`");
        if ($result) {
            $row = $result->fetch_assoc();
            return intval($row['cnt']);
        }
        return 0;
    }
    
    // ID ile veri silme (Inherited Method)
    public function delete(int $id): bool {
        $table = $this->getTableName();
        $stmt = $this->db->prepare("DELETE FROM `$table` WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}

/**
 * AuthManager
 * Personel Giriş/Çıkış işlemlerini yönetir.
 */
class AuthManager extends BaseManager {
    public function getTableName(): string {
        return 'personeller';
    }
    
    public function login(string $username, string $password): bool {
        $stmt = $this->db->prepare("SELECT * FROM `personeller` WHERE kullanici_adi = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stored = $user['sifre'];
            
            // Bcrypt hash mi yoksa düz metin mi kontrol et
            $isHash = (strlen($stored) >= 60 && str_starts_with($stored, '$2'));
            $passwordOk = $isHash
                ? password_verify($password, $stored)   // Hashlenmiş şifre
                : ($password === $stored);               // Düz metin fallback
            
            if ($passwordOk) {
                // Eğer düz metin ise otomatik olarak hashle ve güncelle
                if (!$isHash) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $this->db->prepare("UPDATE `personeller` SET sifre = ? WHERE id = ?");
                    $upd->bind_param("si", $newHash, $user['id']);
                    $upd->execute();
                    $upd->close();
                }
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['ad_soyad'];
                $_SESSION['user_username'] = $user['kullanici_adi'];
                $stmt->close();
                return true;
            }
        }
        $stmt->close();
        return false;
    }
    
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }
    
    public static function logout(): void {
        unset($_SESSION['user_id']);
        unset($_SESSION['user_name']);
        unset($_SESSION['user_username']);
        session_destroy();
    }
}

/**
 * ProductManager
 * İlaç (Ürün) işlemlerini yönetir.
 */
class ProductManager extends BaseManager {
    public function getTableName(): string {
        return 'urunler';
    }
    
    public function add(string $name, int $stock, float $price, string $barcode): bool {
        $stmt = $this->db->prepare("INSERT INTO `urunler` (urun_adi, stok, fiyat, barkod) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sids", $name, $stock, $price, $barcode);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    public function update(int $id, string $name, int $stock, float $price, string $barcode): bool {
        $stmt = $this->db->prepare("UPDATE `urunler` SET urun_adi = ?, stok = ?, fiyat = ?, barkod = ? WHERE id = ?");
        $stmt->bind_param("sidsi", $name, $stock, $price, $barcode, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    // Mevcut stoğa miktar ekle
    public function addStock(int $id, int $amount): bool {
        $stmt = $this->db->prepare("UPDATE `urunler` SET stok = stok + ? WHERE id = ?");
        $stmt->bind_param("ii", $amount, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}

/**
 * CustomerManager
 * Müşteri işlemlerini yönetir.
 */
class CustomerManager extends BaseManager {
    public function getTableName(): string {
        return 'musteriler';
    }
    
    public function add(string $name, string $phone, string $tc, string $adres): bool {
        $stmt = $this->db->prepare("INSERT INTO `musteriler` (ad_soyad, telefon, tc_kimlik, adres) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $phone, $tc, $adres);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    public function update(int $id, string $name, string $phone, string $tc, string $adres): bool {
        $stmt = $this->db->prepare("UPDATE `musteriler` SET ad_soyad = ?, telefon = ?, tc_kimlik = ?, adres = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $phone, $tc, $adres, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}

/**
 * SalesManager
 * Satış geçmişi, raporlar ve sepet işlemlerini yönetir.
 */
class SalesManager extends BaseManager {
    public function getTableName(): string {
        return 'satislar';
    }
    
    // Detaylı satış geçmişini getirir
    public function getSalesHistory(): array {
        $sql = "SELECT s.*, u.urun_adi, u.barkod, m.ad_soyad as musteri_adi 
                FROM `satislar` s 
                LEFT JOIN `urunler` u ON s.urun_id = u.id 
                LEFT JOIN `musteriler` m ON s.musteri_id = m.id 
                ORDER BY s.tarih DESC";
        $result = $this->db->query($sql);
        $history = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }
        }
        return $history;
    }
    
    // Raporlama ve Dashboard Analiz Verileri
    public function getDashboardStats(): array {
        // 1. Toplam Ciro
        $resRev = $this->db->query("SELECT SUM(toplam_tutar) as total_rev FROM `satislar`");
        $rowRev = $resRev->fetch_assoc();
        $totalRev = $rowRev['total_rev'] ? floatval($rowRev['total_rev']) : 0.0;
        
        // 2. Toplam Satış Adeti
        $resCount = $this->db->query("SELECT COUNT(*) as total_sales FROM `satislar`");
        $rowCount = $resCount->fetch_assoc();
        $totalSales = intval($rowCount['total_sales']);
        
        // 3. Toplam Kayıtlı İlaç Sayısı
        $resProducts = $this->db->query("SELECT COUNT(*) as total_products FROM `urunler`");
        $rowProducts = $resProducts->fetch_assoc();
        $totalProducts = intval($rowProducts['total_products']);
        
        // 4. Kritik Stokta Olan İlaç Sayısı (Stok < 15)
        $resLowStock = $this->db->query("SELECT COUNT(*) as low_stock FROM `urunler` WHERE stok < 15");
        $rowLowStock = $resLowStock->fetch_assoc();
        $totalLowStock = intval($rowLowStock['low_stock']);
        
        // 5. En Çok Satan İlk 5 İlaç
        $sqlPopular = "SELECT u.urun_adi, SUM(s.adet) as toplam_adet 
                       FROM `satislar` s 
                       JOIN `urunler` u ON s.urun_id = u.id 
                       GROUP BY s.urun_id 
                       ORDER BY toplam_adet DESC LIMIT 5";
        $resPopular = $this->db->query($sqlPopular);
        $popularProducts = [];
        if ($resPopular) {
            while ($row = $resPopular->fetch_assoc()) {
                $popularProducts[] = $row;
            }
        }
        
        // 6. En Son Kritik Stoka Düşen 5 İlaç
        $sqlLowList = "SELECT urun_adi, stok, fiyat, barkod FROM `urunler` WHERE stok < 15 ORDER BY stok ASC LIMIT 5";
        $resLowList = $this->db->query($sqlLowList);
        $lowStockList = [];
        if ($resLowList) {
            while ($row = $resLowList->fetch_assoc()) {
                $lowStockList[] = $row;
            }
        }
        
        return [
            'total_revenue' => $totalRev,
            'total_sales' => $totalSales,
            'total_products' => $totalProducts,
            'total_low_stock' => $totalLowStock,
            'popular_products' => $popularProducts,
            'low_stock_list' => $lowStockList
        ];
    }
    
    // İşlemsel Sepet Çıkışı (Satış Yapma - Transaction & Stok Düşme)
    public function checkout(array $cart, int $customerId): array {
        if (empty($cart)) {
            return ['success' => false, 'message' => 'Sepet boş olduğu için işlem yapılamadı.'];
        }
        
        // Veritabanı Transaction Başlat
        $this->db->begin_transaction();
        
        try {
            foreach ($cart as $productId => $qty) {
                // İlacı bul ve kilitle (SELECT FOR UPDATE)
                $stmt = $this->db->prepare("SELECT urun_adi, stok, fiyat FROM `urunler` WHERE id = ? FOR UPDATE");
                $stmt->bind_param("i", $productId);
                $stmt->execute();
                $res = $stmt->get_result();
                
                if ($res->num_rows !== 1) {
                    throw new Exception("Ürün bulunamadı (ID: $productId).");
                }
                
                $product = $res->fetch_assoc();
                $stmt->close();
                
                // Stok kontrolü
                if ($product['stok'] < $qty) {
                    throw new Exception("'{$product['urun_adi']}' ilacı için yetersiz stok! (Mevcut: {$product['stok']}, İstenen: $qty)");
                }
                
                // Stok düşme işlemi
                $newStock = $product['stok'] - $qty;
                $stmtUpdate = $this->db->prepare("UPDATE `urunler` SET stok = ? WHERE id = ?");
                $stmtUpdate->bind_param("ii", $newStock, $productId);
                $stmtUpdate->execute();
                $stmtUpdate->close();
                
                // Satış kaydı oluşturma
                $totalPrice = floatval($product['fiyat']) * $qty;
                $stmtSale = $this->db->prepare("INSERT INTO `satislar` (urun_id, musteri_id, adet, toplam_tutar) VALUES (?, ?, ?, ?)");
                
                // Müşteri seçilmediyse (Genel Müşteri) NULL gönder
                $cId = ($customerId > 0) ? $customerId : null;
                $stmtSale->bind_param("iiid", $productId, $cId, $qty, $totalPrice);
                $stmtSale->execute();
                $stmtSale->close();
            }
            
            // Transaction Onayla
            $this->db->commit();
            return ['success' => true, 'message' => 'Satış başarıyla tamamlandı. Stoklar güncellendi.'];
            
        } catch (Exception $e) {
            // Hata oluşursa işlemleri geri al
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

// 5. Servis Nesnelerinin Başlatılması
$authManager = new AuthManager($mysql);
$productManager = new ProductManager($mysql);
$customerManager = new CustomerManager($mysql);
$salesManager = new SalesManager($mysql);

$isLoggedIn = AuthManager::isLoggedIn();

// 6. POST İsteklerinin Yönetimi (Controller)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Giriş İşlemi
    if ($action === 'login') {
        $username = trim($_POST['kullanici_adi'] ?? '');
        $password = $_POST['sifre'] ?? '';
        
        if ($authManager->login($username, $password)) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Hoş geldiniz, ' . $_SESSION['user_name'] . '!'];
            header("Location: index.php?page=dashboard");
            exit;
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Kullanıcı adı veya şifre hatalı!'];
            header("Location: index.php");
            exit;
        }
    }
    
    // Oturum Kontrolü (Giriş dışındaki tüm POST'lar için zorunlu)
    if (!$isLoggedIn) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Lütfen önce giriş yapın.'];
        header("Location: index.php");
        exit;
    }
    
    // Çıkış İşlemi
    if ($action === 'logout') {
        AuthManager::logout();
        session_start();
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Güvenli çıkış yapıldı.'];
        header("Location: index.php");
        exit;
    }
    
    // İLAÇ EKLEME
    if ($action === 'add_product') {
        $name = trim($_POST['urun_adi'] ?? '');
        $stock = intval($_POST['stok'] ?? 0);
        $price = floatval($_POST['fiyat'] ?? 0.0);
        $barcode = trim($_POST['barkod'] ?? '');
        
        if ($name === '' || $barcode === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Lütfen İlaç Adı ve Barkod alanlarını doldurun.'];
        } elseif ($stock < 0 || $price < 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Stok ve Fiyat değerleri negatif olamaz.'];
        } else {
            try {
                if ($productManager->add($name, $stock, $price, $barcode)) {
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'İlaç sisteme başarıyla eklendi.'];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'İlaç eklenemedi (Barkod zaten kayıtlı olabilir).'];
                }
            } catch (Exception $e) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Hata: ' . $e->getMessage()];
            }
        }
        header("Location: index.php?page=products");
        exit;
    }
    
    // İLAÇ GÜNCELLEME
    if ($action === 'update_product') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['urun_adi'] ?? '');
        $stock = intval($_POST['stok'] ?? 0);
        $price = floatval($_POST['fiyat'] ?? 0.0);
        $barcode = trim($_POST['barkod'] ?? '');
        
        if ($id <= 0 || $name === '' || $barcode === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Lütfen tüm zorunlu alanları doldurun.'];
        } elseif ($stock < 0 || $price < 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Stok ve Fiyat değerleri negatif olamaz.'];
        } else {
            try {
                if ($productManager->update($id, $name, $stock, $price, $barcode)) {
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'İlaç bilgileri başarıyla güncellendi.'];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'İlaç güncellenemedi (Barkod başka bir ilaca ait olabilir).'];
                }
            } catch (Exception $e) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Hata: ' . $e->getMessage()];
            }
        }
        header("Location: index.php?page=products");
        exit;
    }
    
    // İLAÇ SİLME
    if ($action === 'delete_product') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            if ($productManager->delete($id)) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'İlaç başarıyla silindi.'];
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'İlaç silinemedi.'];
            }
        }
        header("Location: index.php?page=products");
        exit;
    }

    // STOK EKLEME
    if ($action === 'add_stock') {
        $id = intval($_POST['id'] ?? 0);
        $amount = intval($_POST['miktar'] ?? 0);
        if ($id > 0 && $amount > 0) {
            if ($productManager->addStock($id, $amount)) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => "Stok başarıyla güncellendi. +$amount adet eklendi."];
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Stok güncellenemedi.'];
            }
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Lütfen geçerli bir miktar girin (en az 1).'];
        }
        header("Location: index.php?page=products");
        exit;
    }
    
    // MÜŞTERİ EKLEME
    if ($action === 'add_customer') {
        $name  = trim($_POST['ad_soyad'] ?? '');
        $phone = trim($_POST['telefon']  ?? '');
        $tc    = trim($_POST['tc_kimlik'] ?? '');
        $adres = trim($_POST['adres']    ?? '');

        // TC Kimlik format kontrolü (opsiyonel, doldurulmuşsa 11 haneli olmalı)
        if ($tc !== '' && (strlen($tc) !== 11 || !ctype_digit($tc))) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'TC Kimlik Numarası 11 haneli ve yalnızca rakamlardan oluşmalıdır.'];
        } elseif ($name === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Müşteri Ad Soyad alanı zorunludur.'];
        } else {
            if ($customerManager->add($name, $phone, $tc, $adres)) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Müşteri başarıyla kaydedildi.'];
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Müşteri kaydedilemedi.'];
            }
        }
        header("Location: index.php?page=customers");
        exit;
    }
    
    // MÜŞTERİ GÜNCELLEME
    if ($action === 'update_customer') {
        $id    = intval($_POST['id']       ?? 0);
        $name  = trim($_POST['ad_soyad']   ?? '');
        $phone = trim($_POST['telefon']    ?? '');
        $tc    = trim($_POST['tc_kimlik']  ?? '');
        $adres = trim($_POST['adres']      ?? '');

        if ($tc !== '' && (strlen($tc) !== 11 || !ctype_digit($tc))) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'TC Kimlik Numarası 11 haneli ve yalnızca rakamlardan oluşmalıdır.'];
        } elseif ($id <= 0 || $name === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Müşteri Ad Soyad alanı zorunludur.'];
        } else {
            if ($customerManager->update($id, $name, $phone, $tc, $adres)) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Müşteri bilgileri güncellendi.'];
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Müşteri bilgileri güncellenemedi.'];
            }
        }
        header("Location: index.php?page=customers");
        exit;
    }
    
    // MÜŞTERİ SİLME
    if ($action === 'delete_customer') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            if ($customerManager->delete($id)) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Müşteri kaydı silindi.'];
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Müşteri kaydı silinemedi.'];
            }
        }
        header("Location: index.php?page=customers");
        exit;
    }
    
    // SEPETE EKLEME (+)
    if ($action === 'cart_add') {
        $productId = intval($_POST['product_id'] ?? 0);
        if ($productId > 0) {
            $product = $productManager->findById($productId);
            if ($product) {
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                
                $currentQty = $_SESSION['cart'][$productId] ?? 0;
                if ($product['stok'] > $currentQty) {
                    $_SESSION['cart'][$productId] = $currentQty + 1;
                    $_SESSION['flash'] = ['type' => 'success', 'message' => "'{$product['urun_adi']}' sepete eklendi."];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => "'{$product['urun_adi']}' için stok limitine ulaşıldı."];
                }
            }
        }
        header("Location: index.php?page=sales");
        exit;
    }
    
    // SEPET MİKTAR GÜNCELLEME
    if ($action === 'cart_update') {
        $productId = intval($_POST['product_id'] ?? 0);
        $qty = intval($_POST['qty'] ?? 0);
        
        if ($productId > 0) {
            if ($qty <= 0) {
                unset($_SESSION['cart'][$productId]);
            } else {
                $product = $productManager->findById($productId);
                if ($product) {
                    if ($product['stok'] >= $qty) {
                        $_SESSION['cart'][$productId] = $qty;
                    } else {
                        $_SESSION['cart'][$productId] = $product['stok'];
                        $_SESSION['flash'] = ['type' => 'danger', 'message' => "'{$product['urun_adi']}' ilacında sadece {$product['stok']} adet stok var."];
                    }
                }
            }
        }
        header("Location: index.php?page=sales");
        exit;
    }
    
    // SEPETTEN SİLME
    if ($action === 'cart_remove') {
        $productId = intval($_POST['product_id'] ?? 0);
        if ($productId > 0 && isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
        }
        header("Location: index.php?page=sales");
        exit;
    }
    
    // SEPETİ TEMİZLE
    if ($action === 'cart_clear') {
        $_SESSION['cart'] = [];
        header("Location: index.php?page=sales");
        exit;
    }
    
    // SATIŞI ONAYLA (CHECKOUT)
    if ($action === 'checkout') {
        $customerId = intval($_POST['musteri_id'] ?? 0);
        $cart = $_SESSION['cart'] ?? [];
        
        $res = $salesManager->checkout($cart, $customerId);
        if ($res['success']) {
            $_SESSION['cart'] = [];
            $_SESSION['flash'] = ['type' => 'success', 'message' => $res['message']];
            header("Location: index.php?page=history");
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $res['message']];
            header("Location: index.php?page=sales");
        }
        exit;
    }
}

// 7. GET İstekleri & Sayfalama (Router)
$page = $_GET['page'] ?? 'dashboard';

// Oturum yoksa zorunlu olarak giriş sayfasına yönlendir
if (!$isLoggedIn) {
    $page = 'login';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Derman Eczane Otomasyonu</title>
    <!-- Inter Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Özel Modern Kırmızı Tema CSS Tasarımı -->
    <style>
        :root {
            /* Kırmızı Ton Ağırlıklı Palet */
            --primary: #dc2626;         /* Canlı Kırmızı (Eczane Logosu Rengi) */
            --primary-hover: #b91c1c;   /* Koyu Kırmızı */
            --primary-light: #fef2f2;   /* Çok Açık Pembe/Kırmızı */
            --primary-glow: rgba(220, 38, 38, 0.12);
            --primary-glow-heavy: rgba(220, 38, 38, 0.25);
            
            --bg-main: #f8fafc;         /* Açık Gri/Mavi */
            --bg-card: #ffffff;
            --border-color: #f1f5f9;
            --border-hover: #e2e8f0;
            
            --text-main: #0f172a;       /* Slate 900 */
            --text-muted: #64748b;      /* Slate 500 */
            --text-white: #ffffff;
            
            --success: #10b981;         /* Emerald 500 */
            --warning: #f59e0b;         /* Amber 500 */
            --danger: #ef4444;          /* Red 500 */
            
            --sidebar-width: 280px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 15px -3px rgba(220, 38, 38, 0.03), 0 4px 6px -4px rgba(220, 38, 38, 0.02);
            --shadow-premium: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            --border-radius-lg: 16px;
            --border-radius-md: 12px;
            --border-radius-sm: 8px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
        }

        /* --- 1. GİRİŞ EKRANI STİLLERİ --- */
        .login-wrapper {
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 50%, #fca5a5 100%);
            position: relative;
            overflow: hidden;
        }

        .login-wrapper::before {
            content: '';
            position: absolute;
            width: 800px;
            height: 800px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            top: -200px;
            right: -200px;
            z-index: 1;
        }

        .login-wrapper::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 50%;
            bottom: -100px;
            left: -100px;
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 24px;
            width: 100%;
            max-width: 440px;
            padding: 3rem 2.5rem;
            box-shadow: var(--shadow-premium);
            z-index: 10;
            text-align: center;
            animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .login-logo {
            width: 72px;
            height: 72px;
            background-color: var(--primary);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-white);
            box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);
            margin-bottom: 1.5rem;
            border: 3px solid white;
            animation: pulseGlow 3s infinite;
        }

        .login-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #7f1d1d;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 2.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
            text-align: left;
            position: relative;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-input {
            width: 100%;
            padding: 0.85rem 1rem;
            font-size: 0.95rem;
            border-radius: var(--border-radius-md);
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: var(--text-main);
            transition: all 0.2s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-glow);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.85rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: var(--border-radius-md);
            border: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--text-white);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(220, 38, 38, 0.3);
        }

        .btn-secondary {
            background-color: var(--border-color);
            color: var(--text-main);
        }

        .btn-secondary:hover {
            background-color: var(--border-hover);
        }

        .btn-danger-outline {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-danger-outline:hover {
            background-color: var(--primary-light);
        }

        .login-info-box {
            margin-top: 2rem;
            background-color: rgba(220, 38, 38, 0.05);
            border: 1px dashed rgba(220, 38, 38, 0.2);
            border-radius: var(--border-radius-md);
            padding: 0.85rem;
            font-size: 0.8rem;
            color: #991b1b;
            line-height: 1.4;
        }

        /* --- 2. ANA PANEL LAYOUT --- */
        .app-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Sidebar Stilleri */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--bg-card);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .sidebar-brand {
            padding: 1.75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            background-color: var(--primary);
            color: var(--text-white);
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.35rem;
            border: 2px solid white;
            box-shadow: 0 4px 8px rgba(220, 38, 38, 0.15);
        }

        .brand-text {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        .brand-subtext {
            font-size: 0.75rem;
            color: var(--primary);
            font-weight: 600;
            display: block;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1.5rem 0.75rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            border-radius: var(--border-radius-md);
            transition: all 0.2s ease;
        }

        .menu-link:hover {
            color: var(--primary);
            background-color: var(--primary-light);
        }

        .menu-link.active {
            color: var(--primary);
            background-color: var(--primary-light);
            font-weight: 600;
            box-shadow: inset 4px 0 0 var(--primary);
        }

        .menu-link svg {
            width: 20px;
            height: 20px;
            transition: stroke 0.2s ease;
        }

        .menu-link:hover svg, .menu-link.active svg {
            stroke: var(--primary);
        }

        .sidebar-user {
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--border-color);
            background-color: #fafafa;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            background-color: #fee2e2;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-details {
            flex-grow: 1;
            margin-left: 0.75rem;
            overflow: hidden;
        }

        .user-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .logout-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background-color: #fee2e2;
            color: var(--primary);
        }

        /* Ana İçerik Alanı */
        .main-content {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 2rem 2.5rem;
            background-color: var(--bg-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        .page-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        /* --- 3. DASHBOARD / RAPOR PANELİ --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
        }

        .stat-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--border-hover);
        }

        .stat-info {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .stat-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .stat-value.primary {
            color: var(--primary);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .stat-card-danger {
            border-left: 4px solid var(--danger);
        }

        .stat-card-danger .stat-icon {
            background-color: #fee2e2;
            color: var(--danger);
        }

        /* Dashboard Grafikler & Listeler */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title svg {
            width: 20px;
            height: 20px;
            color: var(--primary);
        }

        /* Popüler İlaçlar Liste Stili */
        .popular-list {
            display: flex;
            flex-direction: column;
            gap: 1.15rem;
        }

        .popular-item {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .popular-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .popular-name {
            color: var(--text-main);
        }

        .popular-count {
            font-weight: 700;
            color: var(--primary);
        }

        .progress-container {
            height: 8px;
            background-color: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary) 0%, #ef4444 100%);
            border-radius: 4px;
            transition: width 0.8s ease;
        }

        /* Kritik Stok Tablo Görünümü */
        .table-simple {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .table-simple th {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .table-simple td {
            font-size: 0.85rem;
            padding: 0.85rem 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .table-simple tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 6px;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: var(--danger);
        }

        .badge-warning {
            background-color: #fef3c7;
            color: #d97706;
        }

        .badge-success {
            background-color: #d1fae5;
            color: var(--success);
        }

        /* --- 4. SATIŞ PANELİ (ÜRÜN KARTLARI & SEPET) --- */
        .sales-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 1.5rem;
            align-items: start;
        }

        .search-filter-bar {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-md);
            padding: 1rem;
            margin-bottom: 1.25rem;
            display: flex;
            gap: 1rem;
            box-shadow: var(--shadow-sm);
        }

        .search-input-wrapper {
            position: relative;
            flex-grow: 1;
        }

        .search-input-wrapper svg {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            width: 18px;
            height: 18px;
        }

        .search-input-wrapper input {
            padding-left: 2.5rem;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.15rem;
        }

        .product-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-glow-heavy);
        }

        .product-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .product-category {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.35rem;
            line-height: 1.3;
            min-height: 2.6rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-barcode {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-family: monospace;
            margin-bottom: 0.75rem;
        }

        .product-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 0.85rem;
            border-top: 1px solid var(--border-color);
        }

        .product-price {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--primary);
        }

        .add-to-cart-btn {
            width: 36px;
            height: 36px;
            background-color: var(--primary);
            color: var(--text-white);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            box-shadow: 0 4px 8px var(--primary-glow-heavy);
        }

        .add-to-cart-btn:hover:not(:disabled) {
            background-color: var(--primary-hover);
            transform: scale(1.05);
        }

        .add-to-cart-btn:disabled {
            background-color: var(--border-hover);
            color: var(--text-muted);
            cursor: not-allowed;
            box-shadow: none;
        }

        /* Sepet Paneli */
        .cart-panel {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            padding: 1.5rem;
            position: sticky;
            top: 2rem;
        }

        .cart-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .cart-badge {
            background-color: var(--primary);
            color: var(--text-white);
            padding: 0.15rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 20px;
            font-weight: 700;
        }

        .cart-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-height: 280px;
            overflow-y: auto;
            margin-bottom: 1.25rem;
            padding-right: 0.25rem;
        }

        .cart-list::-webkit-scrollbar {
            width: 4px;
        }
        .cart-list::-webkit-scrollbar-thumb {
            background-color: var(--border-hover);
            border-radius: 4px;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 0.85rem;
            border-bottom: 1px dashed var(--border-color);
        }

        .cart-item-details {
            flex-grow: 1;
            margin-right: 0.5rem;
        }

        .cart-item-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.2rem;
        }

        .cart-item-price {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .cart-qty-control {
            display: flex;
            align-items: center;
            border: 1px solid var(--border-hover);
            border-radius: 8px;
            overflow: hidden;
            background-color: var(--bg-main);
        }

        .cart-qty-btn {
            background: none;
            border: none;
            width: 24px;
            height: 24px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            transition: background 0.15s;
        }

        .cart-qty-btn:hover {
            background-color: var(--border-color);
            color: var(--primary);
        }

        .cart-qty-value {
            font-size: 0.8rem;
            font-weight: 700;
            width: 24px;
            text-align: center;
        }

        .cart-item-remove {
            color: var(--text-muted);
            background: none;
            border: none;
            cursor: pointer;
            margin-left: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.15s;
        }

        .cart-item-remove:hover {
            color: var(--danger);
            background-color: #fee2e2;
        }

        .cart-empty {
            text-align: center;
            padding: 2.5rem 0;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }

        .cart-empty svg {
            width: 48px;
            height: 48px;
            stroke-width: 1.5;
            color: var(--text-muted);
        }

        .cart-total-box {
            background-color: var(--bg-main);
            border-radius: var(--border-radius-md);
            padding: 1rem;
            margin-bottom: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-total-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .cart-total-value {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--primary);
        }

        .checkout-form {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        /* --- 5. TABLO & CRUD GÖRÜNÜMLERİ --- */
        .table-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .table-wrapper {
            overflow-x: auto;
            width: 100%;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .main-table th {
            background-color: #fafafa;
            padding: 1rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
        }

        .main-table td {
            padding: 1rem 1.5rem;
            font-size: 0.875rem;
            color: var(--text-main);
            border-bottom: 1px solid var(--border-color);
        }

        .main-table tbody tr:hover {
            background-color: #fafafa;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-muted);
            background-color: var(--bg-main);
        }

        .btn-action:hover {
            color: var(--primary);
            background-color: var(--primary-light);
        }

        .btn-action-delete:hover {
            color: var(--danger);
            background-color: #fee2e2;
        }

        /* --- 6. MODAL STİLLERİ --- */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.2s ease-out;
        }

        .modal-content {
            background-color: var(--bg-card);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-premium);
            width: 100%;
            max-width: 480px;
            padding: 2rem;
            position: relative;
            animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.15s;
        }

        .modal-close:hover {
            color: var(--primary);
        }

        .modal-footer {
            margin-top: 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        /* --- 7. BİLDİRİM / FLASH ALERTS --- */
        .alert-box {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-width: 380px;
            width: 100%;
        }

        .alert {
            background-color: var(--bg-card);
            border-radius: var(--border-radius-md);
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-premium);
            border-left: 4px solid var(--primary);
            animation: slideInLeft 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .alert-content-wrapper {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-icon {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .alert-text {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .alert-success {
            border-left-color: var(--success);
        }
        .alert-success .alert-icon {
            color: var(--success);
        }

        .alert-danger {
            border-left-color: var(--danger);
        }
        .alert-danger .alert-icon {
            color: var(--danger);
        }

        .alert-close-btn {
            background: none;
            border: none;
            font-size: 1.1rem;
            cursor: pointer;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.2rem;
        }

        .alert-close-btn:hover {
            color: var(--text-main);
        }

        /* --- 8. ANİMASYONLAR VE DİĞER --- */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulseGlow {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(220, 38, 38, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0);
            }
        }
    </style>
</head>
<body>

    <!-- Bildirim Baloncukları (Flash Alerts) -->
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert-box">
            <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?>" id="sys-alert">
                <div class="alert-content-wrapper">
                    <div class="alert-icon">
                        <?php if ($_SESSION['flash']['type'] === 'success'): ?>
                            <!-- Success SVG -->
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <?php else: ?>
                            <!-- Danger SVG -->
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <?php endif; ?>
                    </div>
                    <div class="alert-text"><?php echo htmlspecialchars($_SESSION['flash']['message']); ?></div>
                </div>
                <button class="alert-close-btn" onclick="document.getElementById('sys-alert').remove()">&times;</button>
            </div>
        </div>
        <script>
            // Bildirimi 4 saniye sonra otomatik kaldır
            setTimeout(function() {
                var alert = document.getElementById('sys-alert');
                if (alert) {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(function() { alert.remove(); }, 500);
                }
            }, 4000);
        </script>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <?php if ($page === 'login'): ?>
        <!-- ==================== GİRİŞ EKRANI ==================== -->
        <div class="login-wrapper">
            <div class="login-card">
                <div class="login-logo">E</div>
                <h1 class="login-title">Derman Eczanesi</h1>
                <p class="login-subtitle">Eczane Nesne Otomasyon Yönetimi</p>
                
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label class="form-label" for="kullanici_adi">Kullanıcı Adı</label>
                        <input class="form-input" type="text" name="kullanici_adi" id="kullanici_adi" placeholder="Örn: admin" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="sifre">Şifre</label>
                        <input class="form-input" type="password" name="sifre" id="sifre" placeholder="••••••" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
                        <!-- Giriş Anahtar SVG -->
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                        Sistem Girişi Yap
                    </button>
                </form>
            </div>
        </div>
        
    <?php else: ?>
        <!-- ==================== ANA UYGULAMA LAYOUT ==================== -->
        <div class="app-container">
            
            <!-- Sol Sidebar Menü -->
            <aside class="sidebar">
                <div class="sidebar-brand">
                    <div class="brand-logo">E</div>
                    <div>
                        <div class="brand-text">Derman Eczanesi</div>
                        <div class="brand-subtext">Otomasyon Sistemi</div>
                    </div>
                </div>
                
                <ul class="sidebar-menu">
                    <li>
                        <a href="index.php?page=dashboard" class="menu-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                            <!-- Grafik SVG -->
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z"></path></svg>
                            Dashboard & Raporlar
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=sales" class="menu-link <?php echo $page === 'sales' ? 'active' : ''; ?>">
                            <!-- Sepet SVG -->
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Yeni Satış Yap
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=products" class="menu-link <?php echo $page === 'products' ? 'active' : ''; ?>">
                            <!-- İlaç Hap SVG -->
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                            İlaç Yönetimi
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=customers" class="menu-link <?php echo $page === 'customers' ? 'active' : ''; ?>">
                            <!-- Müşteriler SVG -->
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Müşteri Yönetimi
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=history" class="menu-link <?php echo $page === 'history' ? 'active' : ''; ?>">
                            <!-- Saat Geçmiş SVG -->
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Satış Geçmişi
                        </a>
                    </li>
                </ul>
                
                <div class="sidebar-user">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['user_name'], 0, 2)); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                        <div class="user-role">Sistem Yöneticisi</div>
                    </div>
                    <form action="index.php" method="POST" onsubmit="return confirm('Çıkış yapmak istediğinize emin misiniz?');">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="logout-btn" title="Güvenli Çıkış">
                            <!-- Çıkış SVG -->
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        </button>
                    </form>
                </div>
            </aside>
            
            <!-- Sağ Ana Bölüm -->
            <main class="main-content">
                
                <?php if ($page === 'dashboard'): 
                    $stats = $salesManager->getDashboardStats();
                ?>
                    <!-- ==================== DASHBOARD PANELİ ==================== -->
                    <div class="content-header">
                        <div>
                            <h2 class="page-title">Dashboard & Raporlar</h2>
                            <p class="page-subtitle">Eczane genel performans analizleri ve kritik stok durumları.</p>
                        </div>
                    </div>
                    
                    <!-- İstatistik Kartları -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-info">
                                <span class="stat-label">Toplam Ciro</span>
                                <span class="stat-value primary"><?php echo number_format($stats['total_revenue'], 2, ',', '.'); ?> ₺</span>
                            </div>
                            <div class="stat-icon">
                                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-info">
                                <span class="stat-label">Yapılan Satışlar</span>
                                <span class="stat-value"><?php echo $stats['total_sales']; ?> Adet</span>
                            </div>
                            <div class="stat-icon">
                                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-info">
                                <span class="stat-label">Kayıtlı İlaçlar</span>
                                <span class="stat-value"><?php echo $stats['total_products']; ?> Çeşit</span>
                            </div>
                            <div class="stat-icon">
                                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                            </div>
                        </div>
                        
                        <div class="stat-card <?php echo $stats['total_low_stock'] > 0 ? 'stat-card-danger' : ''; ?>">
                            <div class="stat-info">
                                <span class="stat-label">Kritik Stok Uyarısı</span>
                                <span class="stat-value <?php echo $stats['total_low_stock'] > 0 ? 'danger' : ''; ?>"><?php echo $stats['total_low_stock']; ?> Ürün</span>
                            </div>
                            <div class="stat-icon">
                                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Grafikler ve Tablolar Bölümü -->
                    <div class="dashboard-grid">
                        
                        <!-- Sol Kolon: En Çok Satan İlaçlar -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                                    En Çok Satan İlaçlar (Top 5)
                                </h3>
                            </div>
                            
                            <div class="popular-list">
                                <?php if (empty($stats['popular_products'])): ?>
                                    <div class="cart-empty" style="padding: 1.5rem 0;">Henüz satış yapılmamış.</div>
                                <?php else: 
                                    $maxAdet = 1;
                                    foreach ($stats['popular_products'] as $item) {
                                        if ($item['toplam_adet'] > $maxAdet) $maxAdet = $item['toplam_adet'];
                                    }
                                    foreach ($stats['popular_products'] as $item): 
                                        $percent = ($item['toplam_adet'] / $maxAdet) * 100;
                                    ?>
                                        <div class="popular-item">
                                            <div class="popular-info">
                                                <span class="popular-name"><?php echo htmlspecialchars($item['urun_adi']); ?></span>
                                                <span class="popular-count"><?php echo $item['toplam_adet']; ?> Adet</span>
                                            </div>
                                            <div class="progress-container">
                                                <div class="progress-bar" style="width: <?php echo $percent; ?>%;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Sağ Kolon: Kritik Stok Uyarıları -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    Kritik Stok Uyarısı (< 15)
                                </h3>
                            </div>
                            
                            <?php if (empty($stats['low_stock_list'])): ?>
                                <div class="cart-empty" style="padding: 1.5rem 0;">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--success);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Kritik stok seviyesinde ürün yok. Harika!
                                </div>
                            <?php else: ?>
                                <table class="table-simple">
                                    <thead>
                                        <tr>
                                            <th>Barkod</th>
                                            <th>İlaç Adı</th>
                                            <th style="text-align: right;">Stok</th>
                                            <th style="text-align: right;">Hızlı İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['low_stock_list'] as $item): ?>
                                            <tr>
                                                <td style="font-family: monospace; font-size: 0.8rem;"><?php echo htmlspecialchars($item['barkod']); ?></td>
                                                <td style="font-weight: 500;"><?php echo htmlspecialchars($item['urun_adi']); ?></td>
                                                <td style="text-align: right;">
                                                    <span class="badge <?php echo $item['stok'] == 0 ? 'badge-danger' : 'badge-warning'; ?>">
                                                        <?php echo $item['stok']; ?> Adet
                                                    </span>
                                                </td>
                                                <td style="text-align: right;">
                                                    <a href="index.php?page=products" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; border-radius: 6px; width: auto; display: inline-flex;">
                                                        Stok Ekle
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                
                <?php elseif ($page === 'sales'): 
                    $products = $productManager->getAll('urun_adi ASC');
                    $customers = $customerManager->getAll('ad_soyad ASC');
                    $cart = $_SESSION['cart'] ?? [];
                ?>
                    <!-- ==================== SATIŞ PANELİ (KARTLAR VE SEPET) ==================== -->
                    <div class="content-header">
                        <div>
                            <h2 class="page-title">Yeni Satış Yap</h2>
                            <p class="page-subtitle">İlaç seçin, sepete ekleyin ve satışı tamamlayın.</p>
                        </div>
                    </div>
                    
                    <div class="sales-layout">
                        <!-- Sol Bölüm: Ürün Arama & Kartları -->
                        <div>
                            <div class="search-filter-bar">
                                <div class="search-input-wrapper">
                                    <!-- Arama Büyüteç SVG -->
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    <input type="text" class="form-input" id="search-product" placeholder="İlaç adı veya barkod ile canlı ara..." onkeyup="filterProductCards()">
                                </div>
                                
                                <select class="form-input" id="filter-stock" style="width: 180px;" onchange="filterProductCards()">
                                    <option value="all">Tüm Stok Durumları</option>
                                    <option value="instock">Mevcut Ürünler</option>
                                    <option value="lowstock">Kritik Stoklar (< 15)</option>
                                    <option value="outofstock">Tükenenler</option>
                                </select>
                            </div>
                            
                            <div class="products-grid" id="product-cards-container">
                                <?php if (empty($products)): ?>
                                    <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 3rem 0; color: var(--text-muted);">
                                        Sistemde kayıtlı ilaç bulunamadı. Lütfen önce İlaç Yönetimi panelinden ilaç ekleyin.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($products as $p): 
                                        $cartQty = $cart[$p['id']] ?? 0;
                                        $realStock = $p['stok'] - $cartQty;
                                        
                                        // Stok durum belirteci
                                        $stockStatus = 'instock';
                                        if ($p['stok'] == 0) {
                                            $stockStatus = 'outofstock';
                                        } elseif ($p['stok'] < 15) {
                                            $stockStatus = 'lowstock';
                                        }
                                    ?>
                                        <div class="product-card" data-name="<?php echo htmlspecialchars(mb_strtolower($p['urun_adi'], 'UTF-8')); ?>" data-barcode="<?php echo htmlspecialchars($p['barkod']); ?>" data-stock-status="<?php echo $stockStatus; ?>">
                                            <div>
                                                <div class="product-card-header">
                                                    <span class="product-category">İlaç</span>
                                                    <div>
                                                        <?php if ($p['stok'] == 0): ?>
                                                            <span class="badge badge-danger">Tükendi</span>
                                                        <?php elseif ($p['stok'] < 15): ?>
                                                            <span class="badge badge-warning">Kritik (<?php echo $p['stok']; ?>)</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-success"><?php echo $p['stok']; ?> Adet</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <h4 class="product-name"><?php echo htmlspecialchars($p['urun_adi']); ?></h4>
                                                <div class="product-barcode">Barkod: <?php echo htmlspecialchars($p['barkod']); ?></div>
                                            </div>
                                            
                                            <div class="product-card-footer">
                                                <div class="product-price"><?php echo number_format($p['fiyat'], 2, ',', '.'); ?> ₺</div>
                                                
                                                <form action="index.php?page=sales" method="POST">
                                                    <input type="hidden" name="action" value="cart_add">
                                                    <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                                    <button type="submit" class="add-to-cart-btn" <?php echo $realStock <= 0 ? 'disabled' : ''; ?> title="<?php echo $realStock <= 0 ? 'Yetersiz Stok' : 'Sepete Ekle'; ?>">
                                                        <!-- Plus SVG -->
                                                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Sağ Bölüm: Sepet -->
                        <aside class="cart-panel">
                            <h3 class="cart-title">
                                Alışveriş Sepeti
                                <span class="cart-badge"><?php echo count($cart); ?> Kalem</span>
                            </h3>
                            
                            <?php if (empty($cart)): ?>
                                <div class="cart-empty">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    Sepetiniz henüz boş.<br>Sol taraftan ürün ekleyin.
                                </div>
                            <?php else: ?>
                                <div class="cart-list">
                                    <?php 
                                    $grandTotal = 0.0;
                                    foreach ($cart as $productId => $qty): 
                                        $prod = $productManager->findById($productId);
                                        if (!$prod) continue;
                                        $lineTotal = floatval($prod['fiyat']) * $qty;
                                        $grandTotal += $lineTotal;
                                    ?>
                                        <div class="cart-item">
                                            <div class="cart-item-details">
                                                <div class="cart-item-name" title="<?php echo htmlspecialchars($prod['urun_adi']); ?>">
                                                    <?php echo htmlspecialchars($prod['urun_adi']); ?>
                                                </div>
                                                <div class="cart-item-price"><?php echo number_format($prod['fiyat'], 2, ',', '.'); ?> ₺ x <?php echo $qty; ?></div>
                                            </div>
                                            
                                            <div class="cart-qty-control">
                                                <!-- Azalt -->
                                                <form action="index.php?page=sales" method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="cart_update">
                                                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                                                    <input type="hidden" name="qty" value="<?php echo $qty - 1; ?>">
                                                    <button type="submit" class="cart-qty-btn">&minus;</button>
                                                </form>
                                                
                                                <span class="cart-qty-value"><?php echo $qty; ?></span>
                                                
                                                <!-- Arttır -->
                                                <form action="index.php?page=sales" method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="cart_update">
                                                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                                                    <input type="hidden" name="qty" value="<?php echo $qty + 1; ?>">
                                                    <button type="submit" class="cart-qty-btn" <?php echo ($prod['stok'] <= $qty) ? 'disabled style="cursor:not-allowed;"' : ''; ?>>&plus;</button>
                                                </form>
                                            </div>
                                            
                                            <!-- Sil -->
                                            <form action="index.php?page=sales" method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="cart_remove">
                                                <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                                                <button type="submit" class="cart-item-remove" title="Kaldır">
                                                    &times;
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="cart-total-box">
                                    <span class="cart-total-label">Toplam Tutar:</span>
                                    <span class="cart-total-value"><?php echo number_format($grandTotal, 2, ',', '.'); ?> ₺</span>
                                </div>
                                
                                <form action="index.php" method="POST" class="checkout-form" onsubmit="return confirm('Satışı onaylıyor musunuz?');">
                                    <input type="hidden" name="action" value="checkout">
                                    
                                    <div class="form-group" style="margin-bottom: 0.5rem;">
                                        <label class="form-label" for="musteri_id">İşlem Yapılacak Müşteri</label>
                                        <select class="form-input" name="musteri_id" id="musteri_id">
                                            <option value="0">Genel Müşteri (Kayıtlı Olmayan)</option>
                                            <?php foreach ($customers as $c): ?>
                                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['ad_soyad']); ?> (<?php echo htmlspecialchars($c['telefon'] ?: 'Tel yok'); ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">
                                        <!-- Checkmark SVG -->
                                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                        Satışı Tamamla
                                    </button>
                                </form>
                                
                                <form action="index.php?page=sales" method="POST" style="margin-top: 0.75rem; text-align: center;">
                                    <input type="hidden" name="action" value="cart_clear">
                                    <button type="submit" class="btn btn-danger-outline" style="padding: 0.5rem; font-size: 0.85rem;" onclick="return confirm('Sepetteki tüm ürünler çıkarılacaktır. Emin misiniz?');">
                                        Sepeti Boşalt
                                    </button>
                                </form>
                            <?php endif; ?>
                        </aside>
                    </div>
                    
                    <script>
                        // Ürün Kartlarını JS ile Anlık Filtreleme
                        function filterProductCards() {
                            const searchQuery = document.getElementById('search-product').value.toLowerCase();
                            const stockFilter = document.getElementById('filter-stock').value;
                            const cards = document.querySelectorAll('#product-cards-container .product-card');
                            
                            cards.forEach(card => {
                                const name = card.getAttribute('data-name');
                                const barcode = card.getAttribute('data-barcode');
                                const stockStatus = card.getAttribute('data-stock-status');
                                
                                const matchesSearch = name.includes(searchQuery) || barcode.includes(searchQuery);
                                const matchesStock = (stockFilter === 'all') || (stockStatus === stockFilter);
                                
                                if (matchesSearch && matchesStock) {
                                    card.style.display = 'flex';
                                } else {
                                    card.style.display = 'none';
                                }
                            });
                        }
                    </script>
                
                <?php elseif ($page === 'products'): 
                    $products = $productManager->getAll('id DESC');
                ?>
                    <!-- ==================== İLAÇ YÖNETİMİ PANELİ ==================== -->
                    <div class="content-header">
                        <div>
                            <h2 class="page-title">İlaç Yönetimi</h2>
                            <p class="page-subtitle">Sistemdeki ilaçları ekleyin, güncelleyin veya silin.</p>
                        </div>
                        <button class="btn btn-primary" style="width: auto;" onclick="openModal('add-product-modal')">
                            <!-- Artı SVG -->
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                            Yeni İlaç Ekle
                        </button>
                    </div>
                    
                    <div class="table-card">
                        <div class="table-card-header">
                            <h3 class="card-title">Kayıtlı İlaç Listesi</h3>
                            <div class="search-input-wrapper" style="width: 300px;">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                <input type="text" class="form-input" id="search-table-product" placeholder="Tabloda ara..." onkeyup="filterTable('search-table-product', 'products-table-body', [0, 1])">
                            </div>
                        </div>
                        
                        <div class="table-wrapper">
                            <table class="main-table">
                                <thead>
                                    <tr>
                                        <th>Barkod</th>
                                        <th>İlaç Adı</th>
                                        <th style="text-align: right;">Stok</th>
                                        <th style="text-align: right;">Fiyat</th>
                                        <th style="text-align: center; width: 120px;">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody id="products-table-body">
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">Kayıtlı ilaç bulunamadı.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $p): ?>
                                            <tr>
                                                <td style="font-family: monospace; font-weight: 500;"><?php echo htmlspecialchars($p['barkod']); ?></td>
                                                <td style="font-weight: 600;"><?php echo htmlspecialchars($p['urun_adi']); ?></td>
                                                <td style="text-align: right;">
                                                    <?php if ($p['stok'] == 0): ?>
                                                        <span class="badge badge-danger">Tükendi</span>
                                                    <?php elseif ($p['stok'] < 15): ?>
                                                        <span class="badge badge-warning">Kritik (<?php echo $p['stok']; ?>)</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success"><?php echo $p['stok']; ?> Adet</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: right; font-weight: 700; color: var(--primary);"><?php echo number_format($p['fiyat'], 2, ',', '.'); ?> ₺</td>
                                                <td style="text-align: center;">
                                                    <div class="table-actions" style="justify-content: center;">
                                                        <!-- Stok Ekle Butonu -->
                                                        <button class="btn-action" title="Stok Ekle" style="color: var(--success);" onclick="openStockModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['urun_adi']); ?>', <?php echo $p['stok']; ?>)">
                                                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                        </button>

                                                        <!-- Düzenle Butonu -->
                                                        <button class="btn-action" title="Düzenle" onclick="openEditProductModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['urun_adi']); ?>', '<?php echo $p['barkod']; ?>', <?php echo $p['fiyat']; ?>, <?php echo $p['stok']; ?>)">
                                                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                        </button>
                                                        
                                                        <!-- Sil Butonu -->
                                                        <form action="index.php?page=products" method="POST" onsubmit="return confirm('Bu ilacı sistemden tamamen silmek istediğinize emin misiniz?');" style="display:inline;">
                                                            <input type="hidden" name="action" value="delete_product">
                                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                            <button type="submit" class="btn-action btn-action-delete" title="Sil">
                                                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- İLAÇ EKLEME MODALI -->
                    <div class="modal" id="add-product-modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title">Yeni İlaç Kaydet</h3>
                                <button class="modal-close" onclick="closeModal('add-product-modal')">&times;</button>
                            </div>
                            <form action="index.php?page=products" method="POST">
                                <input type="hidden" name="action" value="add_product">
                                
                                <div class="form-group">
                                    <label class="form-label" for="add-barkod">Barkod (Zorunlu ve Benzersiz)</label>
                                    <input class="form-input" type="text" name="barkod" id="add-barkod" required placeholder="Örn: 8699525010017" autocomplete="off">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="add-urun-adi">İlaç Adı (Zorunlu)</label>
                                    <input class="form-input" type="text" name="urun_adi" id="add-urun-adi" required placeholder="Örn: Parol 500 mg Tablet" autocomplete="off">
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label class="form-label" for="add-stok">Mevcut Stok Adeti</label>
                                        <input class="form-input" type="number" name="stok" id="add-stok" required value="0" min="0">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="add-fiyat">Birim Fiyat (₺)</label>
                                        <input class="form-input" type="number" name="fiyat" id="add-fiyat" step="0.01" required value="0.00" min="0">
                                    </div>
                                </div>
                                
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" style="width: auto;" onclick="closeModal('add-product-modal')">İptal</button>
                                    <button type="submit" class="btn btn-primary" style="width: auto;">Kaydet</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- İLAÇ DÜZENLEME MODALI -->
                    <div class="modal" id="edit-product-modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title">İlaç Bilgilerini Düzenle</h3>
                                <button class="modal-close" onclick="closeModal('edit-product-modal')">&times;</button>
                            </div>
                            <form action="index.php?page=products" method="POST">
                                <input type="hidden" name="action" value="update_product">
                                <input type="hidden" name="id" id="edit-id">
                                
                                <div class="form-group">
                                    <label class="form-label" for="edit-barkod">Barkod (Zorunlu ve Benzersiz)</label>
                                    <input class="form-input" type="text" name="barkod" id="edit-barkod" required autocomplete="off">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="edit-urun-adi">İlaç Adı (Zorunlu)</label>
                                    <input class="form-input" type="text" name="urun_adi" id="edit-urun-adi" required autocomplete="off">
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label class="form-label" for="edit-stok">Stok Adeti</label>
                                        <input class="form-input" type="number" name="stok" id="edit-stok" required min="0">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="edit-fiyat">Birim Fiyat (₺)</label>
                                        <input class="form-input" type="number" name="fiyat" id="edit-fiyat" step="0.01" required min="0">
                                    </div>
                                </div>
                                
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" style="width: auto;" onclick="closeModal('edit-product-modal')">İptal</button>
                                    <button type="submit" class="btn btn-primary" style="width: auto;">Değişiklikleri Kaydet</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- STOK EKLEME MODALI -->
                    <div class="modal" id="add-stock-modal">
                        <div class="modal-content" style="max-width: 380px;">
                            <div class="modal-header">
                                <h3 class="modal-title">Stok Ekle</h3>
                                <button class="modal-close" onclick="closeModal('add-stock-modal')">&times;</button>
                            </div>
                            <form action="index.php?page=products" method="POST">
                                <input type="hidden" name="action" value="add_stock">
                                <input type="hidden" name="id" id="stock-product-id">

                                <div style="background: var(--primary-light); border-radius: var(--border-radius-md); padding: 1rem; margin-bottom: 1.25rem; border-left: 4px solid var(--primary);">
                                    <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">İlaç</div>
                                    <div id="stock-product-name" style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-top: 0.25rem;"></div>
                                    <div style="font-size: 0.85rem; color: var(--primary); font-weight: 600; margin-top: 0.25rem;">Mevcut Stok: <span id="stock-current"></span> Adet</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="add-stock-miktar">Eklenecek Miktar (Adet)</label>
                                    <input class="form-input" type="number" name="miktar" id="add-stock-miktar" required min="1" value="1" placeholder="Örn: 50">
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" style="width: auto;" onclick="closeModal('add-stock-modal')">İptal</button>
                                    <button type="submit" class="btn btn-primary" style="width: auto; background: var(--success); box-shadow: none;">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Stok Ekle
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <script>
                        // İlaç Düzenleme Veri Doldurma ve Açma
                        function openEditProductModal(id, name, barcode, price, stock) {
                            document.getElementById('edit-id').value = id;
                            document.getElementById('edit-urun-adi').value = name;
                            document.getElementById('edit-barkod').value = barcode;
                            document.getElementById('edit-fiyat').value = price;
                            document.getElementById('edit-stok').value = stock;
                            openModal('edit-product-modal');
                        }
                        // Stok Ekleme Modal Açma
                        function openStockModal(id, name, currentStock) {
                            document.getElementById('stock-product-id').value = id;
                            document.getElementById('stock-product-name').textContent = name;
                            document.getElementById('stock-current').textContent = currentStock;
                            document.getElementById('add-stock-miktar').value = 1;
                            openModal('add-stock-modal');
                        }
                    </script>
                
                <?php elseif ($page === 'customers'): 
                    $customers = $customerManager->getAll('id DESC');
                ?>
                    <!-- ==================== MÜŞTERİ YÖNETİMİ PANELİ ==================== -->
                    <div class="content-header">
                        <div>
                            <h2 class="page-title">Müşteri Yönetimi</h2>
                            <p class="page-subtitle">Sistemdeki müşterileri ekleyin, güncelleyin veya silin.</p>
                        </div>
                        <button class="btn btn-primary" style="width: auto;" onclick="openModal('add-customer-modal')">
                            <!-- Artı SVG -->
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                            Yeni Müşteri Ekle
                        </button>
                    </div>
                    
                    <div class="table-card">
                        <div class="table-card-header">
                            <h3 class="card-title">Kayıtlı Müşteri Listesi</h3>
                            <div class="search-input-wrapper" style="width: 300px;">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                <input type="text" class="form-input" id="search-table-customer" placeholder="Tabloda ara..." onkeyup="filterTable('search-table-customer', 'customers-table-body', [0, 1])">
                            </div>
                        </div>
                        
                        <div class="table-wrapper">
                            <table class="main-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Adı Soyadı</th>
                                        <th>TC Kimlik</th>
                                        <th>Telefon</th>
                                        <th>Adres</th>
                                        <th style="text-align: center; width: 110px;">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody id="customers-table-body">
                                    <?php if (empty($customers)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">Kayıtlı müşteri bulunamadı.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($customers as $c): ?>
                                            <tr>
                                                <td style="font-weight: 600; color: var(--text-muted);">#<?php echo $c['id']; ?></td>
                                                <td style="font-weight: 600;"><?php echo htmlspecialchars($c['ad_soyad']); ?></td>
                                                <td style="font-family: monospace; font-size:0.82rem;"><?php echo htmlspecialchars($c['tc_kimlik'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($c['telefon'] ?: '-'); ?></td>
                                                <td style="max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($c['adres'] ?? ''); ?>"><?php echo htmlspecialchars($c['adres'] ?: '-'); ?></td>
                                                <td style="text-align: center;">
                                                    <div class="table-actions" style="justify-content: center;">
                                                        <!-- Düzenle Butonu -->
                                                        <button class="btn-action" title="Düzenle" onclick="openEditCustomerModal(<?php echo $c['id']; ?>, '<?php echo addslashes($c['ad_soyad']); ?>', '<?php echo addslashes($c['telefon'] ?: ''); ?>', '<?php echo addslashes($c['tc_kimlik'] ?? ''); ?>', '<?php echo addslashes($c['adres'] ?? ''); ?>')">
                                                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                        </button>
                                                        
                                                        <!-- Sil Butonu -->
                                                        <form action="index.php?page=customers" method="POST" onsubmit="return confirm('Bu müşteri kaydını tamamen silmek istediğinize emin misiniz?');" style="display:inline;">
                                                            <input type="hidden" name="action" value="delete_customer">
                                                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                            <button type="submit" class="btn-action btn-action-delete" title="Sil">
                                                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- MÜŞTERİ EKLEME MODALI -->
                    <div class="modal" id="add-customer-modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title">Yeni Müşteri Kaydet</h3>
                                <button class="modal-close" onclick="closeModal('add-customer-modal')">&times;</button>
                            </div>
                            <form action="index.php?page=customers" method="POST">
                                <input type="hidden" name="action" value="add_customer">
                                
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                    <div class="form-group">
                                        <label class="form-label" for="add-cust-ad">Adı Soyadı (Zorunlu)</label>
                                        <input class="form-input" type="text" name="ad_soyad" id="add-cust-ad" required placeholder="Örn: Selim Demir" autocomplete="off">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="add-cust-tc">TC Kimlik No</label>
                                        <input class="form-input" type="text" name="tc_kimlik" id="add-cust-tc" maxlength="11" placeholder="11 haneli TC No" autocomplete="off">
                                    </div>
                                </div>

                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                    <div class="form-group">
                                        <label class="form-label" for="add-cust-tel">Telefon Numarası</label>
                                        <input class="form-input" type="text" name="telefon" id="add-cust-tel" placeholder="0532 222 33 44" autocomplete="off">
                                    </div>
                                    <div class="form-group" style="grid-column: span 1;"></div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="add-cust-adres">Adres</label>
                                    <textarea class="form-input" name="adres" id="add-cust-adres" rows="2" placeholder="Mahalle, cadde, ilçe, il..." style="resize:vertical;"></textarea>
                                </div>
                                
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" style="width: auto;" onclick="closeModal('add-customer-modal')">İptal</button>
                                    <button type="submit" class="btn btn-primary" style="width: auto;">Kaydet</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- MÜŞTERİ DÜZENLEME MODALI -->
                    <div class="modal" id="edit-customer-modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title">Müşteri Bilgilerini Düzenle</h3>
                                <button class="modal-close" onclick="closeModal('edit-customer-modal')">&times;</button>
                            </div>
                            <form action="index.php?page=customers" method="POST">
                                <input type="hidden" name="action" value="update_customer">
                                <input type="hidden" name="id" id="edit-cust-id">
                                
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                    <div class="form-group">
                                        <label class="form-label" for="edit-cust-ad-soyad">Adı Soyadı (Zorunlu)</label>
                                        <input class="form-input" type="text" name="ad_soyad" id="edit-cust-ad-soyad" required autocomplete="off">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="edit-cust-tc">TC Kimlik No</label>
                                        <input class="form-input" type="text" name="tc_kimlik" id="edit-cust-tc" maxlength="11" autocomplete="off">
                                    </div>
                                </div>

                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                    <div class="form-group">
                                        <label class="form-label" for="edit-cust-telefon">Telefon Numarası</label>
                                        <input class="form-input" type="text" name="telefon" id="edit-cust-telefon" autocomplete="off">
                                    </div>
                                    <div class="form-group" style="grid-column: span 1;"></div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="edit-cust-adres">Adres</label>
                                    <textarea class="form-input" name="adres" id="edit-cust-adres" rows="2" style="resize:vertical;"></textarea>
                                </div>
                                
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" style="width: auto;" onclick="closeModal('edit-customer-modal')">İptal</button>
                                    <button type="submit" class="btn btn-primary" style="width: auto;">Değişiklikleri Kaydet</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <script>
                        // Müşteri Düzenleme Verilerini Doldurma ve Açma
                        function openEditCustomerModal(id, adSoyad, telefon, tc, adres) {
                            document.getElementById('edit-cust-id').value = id;
                            document.getElementById('edit-cust-ad-soyad').value = adSoyad;
                            document.getElementById('edit-cust-telefon').value = telefon;
                            document.getElementById('edit-cust-tc').value = tc;
                            document.getElementById('edit-cust-adres').value = adres;
                            openModal('edit-customer-modal');
                        }
                    </script>
                
                <?php elseif ($page === 'history'): 
                    $sales = $salesManager->getSalesHistory();
                ?>
                    <!-- ==================== SATIŞ GEÇMİŞİ PANELİ ==================== -->
                    <div class="content-header">
                        <div>
                            <h2 class="page-title">Satış Geçmişi</h2>
                            <p class="page-subtitle">Eczaneden gerçekleştirilen tüm satış kayıtları.</p>
                        </div>
                    </div>
                    
                    <div class="table-card">
                        <div class="table-card-header">
                            <h3 class="card-title">Yapılan Satışlar</h3>
                            <div class="search-input-wrapper" style="width: 350px;">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                <input type="text" class="form-input" id="search-table-history" placeholder="Tarih, ilaç veya müşteri ile canlı ara..." onkeyup="filterTable('search-table-history', 'history-table-body', [0, 1, 2])">
                            </div>
                        </div>
                        
                        <div class="table-wrapper">
                            <table class="main-table">
                                <thead>
                                    <tr>
                                        <th>Tarih</th>
                                        <th>İlaç Detayları</th>
                                        <th>Müşteri Bilgisi</th>
                                        <th style="text-align: right; width: 100px;">Adet</th>
                                        <th style="text-align: right; width: 150px;">Toplam Tutar</th>
                                    </tr>
                                </thead>
                                <tbody id="history-table-body">
                                    <?php if (empty($sales)): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">Henüz sisteme kayıtlı bir satış yapılmadı.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($sales as $s): ?>
                                            <tr>
                                                <td style="font-weight: 500; font-size: 0.825rem; color: var(--text-muted);">
                                                    <?php echo date('d.m.Y H:i', strtotime($s['tarih'])); ?>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($s['urun_adi'] ?? 'Silinmiş İlaç'); ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">Barkod: <?php echo htmlspecialchars($s['barkod'] ?? '-'); ?></div>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($s['musteri_adi'] ?? 'Genel Müşteri'); ?></div>
                                                </td>
                                                <td style="text-align: right; font-weight: 600;">
                                                    <?php echo $s['adet']; ?> Adet
                                                </td>
                                                <td style="text-align: right; font-weight: 700; color: var(--primary); font-size: 0.95rem;">
                                                    <?php echo number_format($s['toplam_tutar'], 2, ',', '.'); ?> ₺
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                
                <?php endif; ?>
                
            </main>
        </div>
    <?php endif; ?>

    <!-- MODAL AÇMA / KAPAMA VE TABLO ARAMA YARDIMCI SCRIPTLERI -->
    <script>
        // Modal Yönetimi
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Dışarı tıklanınca modalları kapat
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Tablo içi Anlık Arama Algoritması
        function filterTable(inputId, tbodyId, columnsToSearch) {
            const query = document.getElementById(inputId).value.toLowerCase();
            const rows = document.querySelectorAll('#' + tbodyId + ' tr');
            
            rows.forEach(row => {
                let match = false;
                
                // Belirtilen kolonlarda (indekslerde) arama yap
                for (let i = 0; i < columnsToSearch.length; i++) {
                    const colIndex = columnsToSearch[i];
                    const td = row.cells[colIndex];
                    if (td) {
                        const cellText = td.textContent || td.innerText;
                        if (cellText.toLowerCase().includes(query)) {
                            match = true;
                            break;
                        }
                    }
                }
                
                if (match || query === "") {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }
    </script>
</body>
</html>
<?php 
// 8. Veritabanı bağlantısını kapat
$mysql->close();
?>
