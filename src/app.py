import os
import shutil
import uuid
import hashlib
import tkinter as tk
from tkinter import filedialog, messagebox, ttk
import customtkinter as ctk
from datetime import datetime

# Veritabanı fonksiyonlarının içe aktarılması
from database import (
    dogrula_kullanici,
    musterileri_getir,
    musterileri_detayli_getir,
    musteri_ekle,
    gecmis_listesi_getir,
    ses_kaydi_soft_delete,
    ses_kaydi_bul_seri,
    ses_kaydi_ekle,
    hash_var_mi,
    rolleri_getir,
    personel_ekle,
    personelleri_getir
)

# CustomTkinter Tema ve Renk Paleti Ayarları
ctk.set_appearance_mode("Dark")
ctk.set_default_color_theme("blue")  # Modern mavi tema

def dosya_hash_hesapla(dosya_yolu):
    """
    Seçilen ses dosyasının benzersiz SHA-256 hash değerini hesaplar.
    """
    hasher = hashlib.sha256()
    try:
        with open(dosya_yolu, 'rb') as f:
            for chunk in iter(lambda: f.read(65536), b''):
                hasher.update(chunk)
        return hasher.hexdigest()
    except Exception as e:
        print(f"Hash hesaplama hatası: {e}")
        return None

class AudioCryptApp(ctk.CTk):
    def __init__(self):
        super().__init__()

        # Pencere Boyut ve Başlık Ayarları
        self.title("AudioCrypt Enterprise - Dijital Hak Yönetimi & Telif Koruma")
        self.geometry("1024x680")
        self.minsize(950, 620)

        # Aktif Kullanıcı ve Rol Bilgisi (Başlangıçta Boş)
        self.current_user_id = None
        self.current_user = None
        self.current_role = None
        self.current_permissions = {}

        # Müşteri ve Rol verileri için yerel eşlemeler
        self.clients_map = {}
        self.roles_map = {}

        # Varsayılan Girdi/Çıktı klasörlerini hazırla
        self.default_input_dir = os.path.abspath("input_audio")
        self.default_output_dir = os.path.abspath("output_audio")
        os.makedirs(self.default_input_dir, exist_ok=True)
        os.makedirs(self.default_output_dir, exist_ok=True)

        # Seçilen lisanslanacak dosya yolu
        self.selected_licensing_file = None

        # Ana konteynerler
        self.login_frame = None
        self.main_frame = None

        # Giriş Ekranını Göster
        self.show_login_screen()

    # =========================================================================
    # GİRİŞ EKRANI (LOGIN SCREEN)
    # =========================================================================
    def show_login_screen(self):
        if self.main_frame:
            self.main_frame.pack_forget()

        # Giriş Frame
        self.login_frame = ctk.CTkFrame(self, fg_color="transparent")
        self.login_frame.pack(expand=True, fill="both")

        # Giriş Kartı (Merkezi Panel)
        login_card = ctk.CTkFrame(self.login_frame, width=400, height=450, corner_radius=15)
        login_card.place(relx=0.5, rely=0.5, anchor="center")

        # Logo / Simge
        logo_label = ctk.CTkLabel(login_card, text="🔊", font=("Arial", 48))
        logo_label.pack(pady=(30, 5))

        title_label = ctk.CTkLabel(login_card, text="AudioCrypt Enterprise", font=("Arial", 22, "bold"))
        title_label.pack(pady=(0, 5))

        subtitle_label = ctk.CTkLabel(login_card, text="Lütfen kurumsal bilgilerinizle giriş yapın", font=("Arial", 12), text_color="gray")
        subtitle_label.pack(pady=(0, 30))

        # Giriş Giriş Alanları
        self.username_entry = ctk.CTkEntry(login_card, placeholder_text="Kullanıcı Adı", width=280, height=40)
        self.username_entry.pack(pady=10)

        self.password_entry = ctk.CTkEntry(login_card, placeholder_text="Şifre", show="*", width=280, height=40)
        self.password_entry.pack(pady=10)

        # Hızlı Rol Testi için Bilgilendirme Notu
        info_label = ctk.CTkLabel(
            login_card, 
            text="Veritabanı Kullanıcıları:\nadmin (Yönetici)  |  prod (Prodüktör)  |  staj (Stajyer)\nŞifreler: 123456", 
            font=("Arial", 10), 
            text_color="gray50"
        )
        info_label.pack(pady=10)

        # Giriş Butonu
        login_btn = ctk.CTkButton(
            login_card, 
            text="Sisteme Giriş Yap", 
            font=("Arial", 14, "bold"), 
            width=280, 
            height=40, 
            fg_color="#525fe1", 
            hover_color="#3d49b8",
            command=self.handle_login
        )
        login_btn.pack(pady=(15, 30))

    def handle_login(self):
        username = self.username_entry.get().strip().lower()
        password = self.password_entry.get().strip()

        if not username or not password:
            messagebox.showwarning("Uyarı", "Lütfen tüm alanları doldurun!")
            return

        user_info = dogrula_kullanici(username, password)
        
        if user_info:
            self.current_user_id = user_info["id"]
            self.current_user = user_info["ad_soyad"]
            self.current_role = user_info["rol_adi"]
            self.current_permissions = user_info
            
            # Giriş Başarılı, Ana Ekranı Kur
            self.login_frame.pack_forget()
            self.show_main_screen()
        else:
            messagebox.showerror("Hata", "Hatalı kullanıcı adı veya şifre!")

    # =========================================================================
    # ANA UYGULAMA EKRANI (MAIN APPLICATION SCREEN)
    # =========================================================================
    def show_main_screen(self):
        # Müşterileri veritabanından çek ve eşle
        self.yenile_musteri_listesi()

        # Ana Frame
        self.main_frame = ctk.CTkFrame(self, fg_color="transparent")
        self.main_frame.pack(fill="both", expand=True)

        # 1. SOL PANEL (SIDEBAR)
        sidebar = ctk.CTkFrame(self.main_frame, width=220, corner_radius=0, fg_color="#111111")
        sidebar.pack(side="left", fill="y")
        sidebar.pack_propagate(False)

        # Logo
        app_logo = ctk.CTkLabel(sidebar, text="🔊 AudioCrypt", font=("Arial", 20, "bold"), text_color="#525fe1")
        app_logo.pack(pady=(30, 5))

        version_label = ctk.CTkLabel(sidebar, text="Enterprise v1.3", font=("Arial", 11, "italic"), text_color="gray")
        version_label.pack(pady=(0, 30))

        # Kullanıcı Kartı (Profile Card)
        user_card = ctk.CTkFrame(sidebar, fg_color="#1c1c1c", corner_radius=10)
        user_card.pack(pady=10, padx=15, fill="x")

        user_name_lbl = ctk.CTkLabel(user_card, text=self.current_user, font=("Arial", 12, "bold"))
        user_name_lbl.pack(pady=(10, 2), padx=10)

        role_badge = ctk.CTkLabel(
            user_card, 
            text=self.current_role.upper(), 
            font=("Arial", 9, "bold"), 
            text_color="#2fa572" if self.current_role != "Sistem Yöneticisi" else "#e15252",
            fg_color="#2b2b2b",
            corner_radius=5
        )
        role_badge.pack(pady=(0, 10), padx=10)

        # Sidebar Menü Butonları / Bilgilendirme
        sidebar_spacer = ctk.CTkLabel(sidebar, text="", fg_color="transparent")
        sidebar_spacer.pack(fill="both", expand=True)

        # Çıkış Butonu
        logout_btn = ctk.CTkButton(
            sidebar, 
            text="Oturumu Kapat", 
            fg_color="#333333", 
            hover_color="#e15252",
            command=self.handle_logout
        )
        logout_btn.pack(pady=20, padx=15, fill="x")

        # 2. SAĞ PANEL (İÇERİK ALANI)
        content_area = ctk.CTkFrame(self.main_frame, fg_color="transparent")
        content_area.pack(side="right", fill="both", expand=True, padx=20, pady=20)

        # Sekme Kontrolü (Tabview)
        self.tabview = ctk.CTkTabview(content_area)
        self.tabview.pack(fill="both", expand=True)

        self.tabview.add("Lisanslama Paneli")
        self.tabview.add("Tarayıcı (Scanner)")
        self.tabview.add("İşlem Geçmişi")
        
        # Müşteri yönetimi (Yazma izni olanlar görebilir: Admin ve Prodüktör)
        if self.current_permissions.get("yazma_izni") == 1:
            self.tabview.add("Müşteri Yönetimi")
        
        # Personel yönetimi (Sadece admin görebilir)
        if self.current_permissions.get("admin_izni") == 1:
            self.tabview.add("Personel Yönetimi")

        # Sekmeleri Yapılandır
        self.setup_licensing_tab(self.tabview.tab("Lisanslama Paneli"))
        self.setup_scanner_tab(self.tabview.tab("Tarayıcı (Scanner)"))
        self.setup_history_tab(self.tabview.tab("İşlem Geçmişi"))
        
        if self.current_permissions.get("yazma_izni") == 1:
            self.setup_customer_tab(self.tabview.tab("Müşteri Yönetimi"))
            
        if self.current_permissions.get("admin_izni") == 1:
            self.setup_personnel_tab(self.tabview.tab("Personel Yönetimi"))

    def yenile_musteri_listesi(self):
        musteriler = musterileri_getir()
        self.clients_map = {unvan: cid for cid, unvan in musteriler}

    def handle_logout(self):
        self.current_user_id = None
        self.current_user = None
        self.current_role = None
        self.current_permissions = {}
        self.clients_map = {}
        self.roles_map = {}
        self.selected_licensing_file = None
        if self.main_frame:
            self.main_frame.pack_forget()
        self.show_login_screen()

    # =========================================================================
    # SEKME 1: LİSANSLAMA PANELİ (LICENSING TAB)
    # =========================================================================
    def setup_licensing_tab(self, tab):
        tab.grid_columnconfigure(0, weight=1, uniform="group_lic")
        tab.grid_columnconfigure(1, weight=1, uniform="group_lic")
        tab.grid_rowconfigure(0, weight=1)

        # Sol Panel: Lisanslama Bilgileri Formu
        form_frame = ctk.CTkScrollableFrame(tab, label_text="Eser Lisanslama Formu", label_font=("Arial", 14, "bold"))
        form_frame.grid(row=0, column=0, sticky="nsew", padx=(0, 10), pady=10)

        # Dosya Seçimi
        ctk.CTkLabel(form_frame, text="Lisanslanacak Temiz Ses Dosyası (.wav):", font=("Arial", 12, "bold")).pack(anchor="w", pady=(10, 2))
        file_row = ctk.CTkFrame(form_frame, fg_color="transparent")
        file_row.pack(fill="x", pady=2)
        self.licensing_file_entry = ctk.CTkEntry(file_row, placeholder_text="Lütfen bir dosya seçin...")
        self.licensing_file_entry.pack(side="left", fill="x", expand=True, padx=(0, 5))
        self.btn_select_lic_file = ctk.CTkButton(file_row, text="Dosya Seç", width=80, command=self.select_licensing_file_picker)
        self.btn_select_lic_file.pack(side="right")

        # Eser Adı
        ctk.CTkLabel(form_frame, text="Eser (Şarkı/Kayıt) Adı:", font=("Arial", 12, "bold")).pack(anchor="w", pady=(15, 2))
        self.track_name_entry = ctk.CTkEntry(form_frame, placeholder_text="Örn: Intro Final Mix")
        self.track_name_entry.pack(fill="x", pady=2)

        # Sanatçı Adı
        ctk.CTkLabel(form_frame, text="Sanatçı / Yorumcu Adı:", font=("Arial", 12, "bold")).pack(anchor="w", pady=(15, 2))
        self.artist_name_entry = ctk.CTkEntry(form_frame, placeholder_text="Örn: Emre Tuncer")
        self.artist_name_entry.pack(fill="x", pady=2)

        # Telif Sahibi (Müşteri Dropdown)
        ctk.CTkLabel(form_frame, text="Telif Sahibi (Müşteri):", font=("Arial", 12, "bold")).pack(anchor="w", pady=(15, 2))
        client_names = list(self.clients_map.keys())
        self.lic_client_combobox = ctk.CTkComboBox(form_frame, values=client_names)
        self.lic_client_combobox.pack(fill="x", pady=2)
        if client_names:
            self.lic_client_combobox.set(client_names[0])

        # Başlat Butonu
        self.btn_run_licensing = ctk.CTkButton(
            form_frame, 
            text="Lisansla ve Dijital Mühür Göm", 
            fg_color="#2fa572", 
            hover_color="#248259", 
            font=("Arial", 14, "bold"),
            height=45,
            command=self.run_audio_licensing
        )
        self.btn_run_licensing.pack(fill="x", pady=35)

        # Yetki Kontrolü
        if self.current_permissions.get("yazma_izni") != 1:
            self.btn_run_licensing.configure(state="disabled", fg_color="gray30", text="Lisanslama Yetkiniz Yok")
            self.btn_select_lic_file.configure(state="disabled")
            self.track_name_entry.configure(state="disabled")
            self.artist_name_entry.configure(state="disabled")
            self.lic_client_combobox.configure(state="disabled")

        # Sağ Panel: Canlı Log / İzleme
        log_frame = ctk.CTkFrame(tab)
        log_frame.grid(row=0, column=1, sticky="nsew", padx=(10, 0), pady=10)
        log_frame.grid_rowconfigure(1, weight=1)
        log_frame.grid_columnconfigure(0, weight=1)

        ctk.CTkLabel(log_frame, text="Lisanslama Konsol Günlüğü", font=("Arial", 14, "bold")).grid(row=0, column=0, sticky="w", padx=15, pady=10)
        
        self.log_textbox = ctk.CTkTextbox(log_frame, font=("Courier", 12), state="disabled")
        self.log_textbox.grid(row=1, column=0, sticky="nsew", padx=15, pady=(0, 15))
        
        self.append_log("[HAZIR] Lisanslama paneli aktif. Dosya yüklenmesi bekleniyor.")

    def select_licensing_file_picker(self):
        path = filedialog.askopenfilename(filetypes=[("Ses Dosyası", "*.wav")])
        if path:
            self.selected_licensing_file = path
            self.licensing_file_entry.delete(0, tk.END)
            self.licensing_file_entry.insert(0, path)
            self.append_log(f"[SEÇİLDİ] Dosya yüklendi: {os.path.basename(path)}")

    def run_audio_licensing(self):
        file_path = self.selected_licensing_file
        track_name = self.track_name_entry.get().strip()
        artist_name = self.artist_name_entry.get().strip()
        client_name = self.lic_client_combobox.get()
        client_id = self.clients_map.get(client_name)

        if not file_path or not track_name or not artist_name or not client_id:
            messagebox.showwarning("Eksik Bilgi", "Lütfen tüm form alanlarını doldurun ve bir ses dosyası seçin!")
            return

        self.append_log("\n--- LİSANSLAMA İŞLEMİ BAŞLATILDI ---")
        
        # 1. Benzersiz dosya hash'ini al
        self.append_log("[1/5] Ses dosyasının benzersiz hash değeri hesaplanıyor...")
        file_hash = dosya_hash_hesapla(file_path)
        if not file_hash:
            self.append_log("[HATA] Hash hesaplanamadı, işlem iptal edildi.")
            return
        
        self.append_log(f"-> Hash: {file_hash[:20]}...{file_hash[-10:]}")

        # 2. Mükerrer kayıt önleme (Hash kontrolü)
        self.append_log("[2/5] Mükerrer kayıt kontrolü yapılıyor...")
        if hash_var_mi(file_hash):
            self.append_log("[HATA] Bu ses dosyası sistemde zaten lisanslı! Tekrar şifrelenemez.")
            messagebox.showerror("Hata", "Bu ses dosyası daha önce lisanslanmış (mükerrer kayıt)!")
            return
        
        # 3. Seri Numarası Üretimi
        self.append_log("[3/5] Telif için benzersiz Seri Numarası (Watermark ID) üretiliyor...")
        seri_no = f"AC-{uuid.uuid4().hex[:8].upper()}-{datetime.now().strftime('%Y')}"
        self.append_log(f"-> Atanan Seri Numarası: {seri_no}")

        # 4. Dosyaları kopyalama (Giriş/Temiz ve Çıkış/Lisanslı)
        original_filename = os.path.basename(file_path)
        temiz_hedef = os.path.join(self.default_input_dir, f"{seri_no}_temiz.wav")
        lisansli_hedef = os.path.join(self.default_output_dir, f"{seri_no}_lisansli.wav")

        self.append_log("[4/5] Orijinal ses 'input_audio' klasörüne aktarılıyor...")
        try:
            shutil.copy2(file_path, temiz_hedef)
            self.append_log(f"-> Temiz kopya kaydedildi: {os.path.basename(temiz_hedef)}")
            
            # TODO: Gelecek Fazda burası kopyalamak yerine watermark.py çağırarak yazacak
            self.append_log("-> [Robust Watermarking] Frekans alanında (FFT) genlik mühürleme simülasyonu yapılıyor...")
            shutil.copy2(file_path, lisansli_hedef)
            self.append_log(f"-> Lisanslı kopya kaydedildi: {os.path.basename(lisansli_hedef)}")
        except Exception as e:
            self.append_log(f"[HATA] Dosya işlemleri başarısız: {e}")
            return

        # 5. Veritabanına kayıt
        self.append_log("[5/5] Lisans kartı veritabanına işleniyor...")
        basarili = ses_kaydi_ekle(
            seri_no=seri_no,
            orijinal_dosya=original_filename,
            eser_adi=track_name,
            sanatci=artist_name,
            dosya_hash=file_hash,
            musteri_id=client_id,
            personel_id=self.current_user_id
        )

        if basarili:
            self.append_log("[BAŞARILI] Lisanslama işlemi başarıyla tamamlandı!")
            messagebox.showinfo("Başarılı", f"Eser başarıyla lisanslandı!\nAtanan Seri No: {seri_no}")
            
            # Formu temizle
            self.track_name_entry.delete(0, tk.END)
            self.artist_name_entry.delete(0, tk.END)
            self.licensing_file_entry.delete(0, tk.END)
            self.selected_licensing_file = None
            
            # Tabloyu yenile
            if hasattr(self, 'tree'):
                self.populate_history()
        else:
            self.append_log("[HATA] Veritabanı kaydı oluşturulamadı!")

    def append_log(self, text):
        self.log_textbox.configure(state="normal")
        self.log_textbox.insert(tk.END, text + "\n")
        self.log_textbox.see(tk.END)
        self.log_textbox.configure(state="disabled")

    # =========================================================================
    # SEKME 2: TARAYICI (SCANNER TAB)
    # =========================================================================
    def setup_scanner_tab(self, tab):
        tab.grid_columnconfigure(0, weight=1)
        tab.grid_rowconfigure(1, weight=1)

        # Üst Kısım: Dosya Seçme
        top_frame = ctk.CTkFrame(tab, fg_color="transparent")
        top_frame.grid(row=0, column=0, sticky="ew", pady=10, padx=10)
        
        ctk.CTkLabel(top_frame, text="Telif Analizi Yapılacak Şüpheli Ses Dosyası:", font=("Arial", 12, "bold")).pack(side="left", padx=5)
        self.scan_file_entry = ctk.CTkEntry(top_frame, placeholder_text="Seçilen dosya...", width=400)
        self.scan_file_entry.pack(side="left", padx=10, fill="x", expand=True)
        
        btn_select_scan = ctk.CTkButton(top_frame, text="Dosya Seç (.wav)", command=self.select_scan_file)
        btn_select_scan.pack(side="left", padx=5)

        self.btn_run_scan = ctk.CTkButton(
            top_frame, 
            text="Analiz Et (Hash ve Mühür)", 
            fg_color="#525fe1", 
            hover_color="#3d49b8", 
            font=("Arial", 12, "bold"),
            command=self.run_scanner_analysis
        )
        self.btn_run_scan.pack(side="left", padx=10)

        # Alt Kısım: Detaylı Bilgi Kartı
        self.result_card = ctk.CTkFrame(tab, corner_radius=15)
        self.result_card.grid(row=1, column=0, sticky="nsew", pady=10, padx=10)
        self.result_card.grid_columnconfigure(0, weight=1)
        self.result_card.grid_rowconfigure(0, weight=1)

        # Sonuç Boş Ekranı
        self.result_placeholder = ctk.CTkLabel(
            self.result_card, 
            text="Analiz sonuçlarını ve telif kartını görüntülemek için\nyukarıdan bir ses dosyası seçip 'Analiz Et' butonuna tıklayın.",
            font=("Arial", 14),
            text_color="gray"
        )
        self.result_placeholder.pack(expand=True)

        # Telif Sonuç Paneli (Başlangıçta Gizli)
        self.info_panel = ctk.CTkFrame(self.result_card, fg_color="transparent")

    def select_scan_file(self):
        path = filedialog.askopenfilename(filetypes=[("Ses Dosyası", "*.wav")])
        if path:
            self.scan_file_entry.delete(0, tk.END)
            self.scan_file_entry.insert(0, path)

    def run_scanner_analysis(self):
        file_path = self.scan_file_entry.get()
        if not file_path:
            messagebox.showwarning("Uyarı", "Lütfen analiz edilecek bir dosya seçin!")
            return

        self.result_placeholder.pack_forget()
        self.info_panel.pack_forget()

        for widget in self.info_panel.winfo_children():
            widget.destroy()

        # 1. Dosyanın SHA-256 hash'ini al
        uploaded_hash = dosya_hash_hesapla(file_path)
        
        # 2. Veritabanında eşleşen hash sorgula (Eğer temiz veya lisanslı ses doğrudan yüklenirse yakalamak için)
        baglanti = sqlite3.connect("audiocrypt_kurumsal.db")
        imlec = baglanti.cursor()
        imlec.execute("SELECT seri_no FROM ses_kayitlari WHERE dosya_hash = ?", (uploaded_hash,))
        hash_sonuc = imlec.fetchone()
        baglanti.close()

        db_record = None
        if hash_sonuc:
            db_record = ses_kaydi_bul_seri(hash_sonuc[0])
        
        # 3. Eğer hash ile bulunamazsa (veya dosya adı üzerinden sorgulamak gerekirse)
        # TODO: Gelecek adımda burası extracted_serial = extract_watermark(file_path) çağıracak.
        if not db_record:
            # Örnek testler için dosya adından seri no çıkarmayı deneyelim (AC-XXXXXX yapısı varsa)
            filename = os.path.basename(file_path)
            if filename.startswith("AC-"):
                seri_parca = filename.split("_")[0]
                db_record = ses_kaydi_bul_seri(seri_parca)

        if db_record:
            # 1. Başlık
            title = ctk.CTkLabel(self.info_panel, text="🛡️ Telif Kartı ve Sahiplik Bilgisi", font=("Arial", 18, "bold"), text_color="#2fa572")
            title.pack(pady=15)

            # 2. Bilgi Grid
            grid_frame = ctk.CTkFrame(self.info_panel, fg_color="#1e1e1e", corner_radius=10)
            grid_frame.pack(fill="x", padx=40, pady=10)

            labels = [
                ("Seri Numarası (Watermark ID):", db_record["seri_no"]),
                ("Eser Adı:", db_record["eser_adi"]),
                ("Sanatçı / Yorumcu:", db_record["sanatci"]),
                ("Orijinal Dosya Adı:", db_record["orijinal_dosya"]),
                ("Telif Sahibi (Müşteri):", db_record["musteri_adi"]),
                ("Lisanslayan Personel:", db_record["personel_adi"]),
                ("Lisans Tarihi:", db_record["islem_tarihi"]),
                ("Mühür Durumu:", db_record["durum"])
            ]

            for i, (key, value) in enumerate(labels):
                row = ctk.CTkFrame(grid_frame, fg_color="transparent")
                row.pack(fill="x", padx=15, pady=6)
                k_lbl = ctk.CTkLabel(row, text=key, font=("Arial", 12, "bold"), text_color="gray")
                k_lbl.pack(side="left")
                v_lbl = ctk.CTkLabel(row, text=value, font=("Arial", 12, "bold"), text_color="white")
                v_lbl.pack(side="right")
        else:
            # Eşleşme Bulunamadı
            error_title = ctk.CTkLabel(self.info_panel, text="❌ Telif Eşleşmesi Bulunamadı", font=("Arial", 18, "bold"), text_color="#e15252")
            error_title.pack(pady=30)
            
            error_desc = ctk.CTkLabel(
                self.info_panel, 
                text="Bu ses dosyasının içinde geçerli bir dijital mühür (watermark)\nbulunamadı veya veritabanı kayıtları ile eşleşmedi.",
                font=("Arial", 12),
                text_color="gray"
            )
            error_desc.pack()

        self.info_panel.pack(fill="both", expand=True, padx=20, pady=20)

    # =========================================================================
    # SEKME 3: İŞLEM GEÇMİŞİ (HISTORY TAB)
    # =========================================================================
    def setup_history_tab(self, tab):
        tab.grid_columnconfigure(0, weight=1)
        tab.grid_rowconfigure(1, weight=1)

        # Üst Kısım: Arama Barı ve Filtreleme
        top_frame = ctk.CTkFrame(tab, fg_color="transparent")
        top_frame.grid(row=0, column=0, sticky="ew", pady=10, padx=10)

        self.search_entry = ctk.CTkEntry(top_frame, placeholder_text="Seri No, Eser, Sanatçı veya Müşteri ile ara...", width=350)
        self.search_entry.pack(side="left", padx=5)

        btn_search = ctk.CTkButton(top_frame, text="Filtrele", width=80, command=self.filter_history)
        btn_search.pack(side="left", padx=5)

        # Sağ üst: Silme (Soft-delete) butonu
        self.btn_delete_record = ctk.CTkButton(
            top_frame, 
            text="Lisansı Askıya Al (Soft-Delete)", 
            fg_color="#e15252", 
            hover_color="#b83d3d",
            command=self.delete_selected_record
        )
        self.btn_delete_record.pack(side="right", padx=5)

        # Rol Yetki Kontrolü (RBAC) (Silme izni sadece Sistem Yöneticisi / admin_izni olanlardadır)
        if self.current_permissions.get("silme_izni") != 1:
            self.btn_delete_record.configure(state="disabled", fg_color="gray30", text="Silme Yetkiniz Yok")

        # Orta Kısım: Kayıt Tablosu
        table_frame = ctk.CTkFrame(tab)
        table_frame.grid(row=1, column=0, sticky="nsew", pady=10, padx=10)
        table_frame.grid_columnconfigure(0, weight=1)
        table_frame.grid_rowconfigure(0, weight=1)

        # Standart ttk Treeview stili
        style = ttk.Style()
        style.theme_use("default")
        style.configure("Treeview", 
                        background="#2a2a2a", 
                        foreground="white", 
                        rowheight=25, 
                        fieldbackground="#2a2a2a", 
                        bordercolor="#333333", 
                        borderwidth=0)
        style.map('Treeview', background=[('selected', '#525fe1')])
        style.configure("Treeview.Heading", 
                        background="#1f1f1f", 
                        foreground="white", 
                        bordercolor="#333333", 
                        borderwidth=1)

        columns = ("seri_no", "dosya_adi", "eser_adi", "sanatci", "musteri", "personel", "tarih", "durum")
        self.tree = ttk.Treeview(table_frame, columns=columns, show="headings")
        self.tree.grid(row=0, column=0, sticky="nsew")

        # Sütun Başlıkları
        self.tree.heading("seri_no", text="Seri Numarası")
        self.tree.heading("dosya_adi", text="Dosya Adı")
        self.tree.heading("eser_adi", text="Eser Adı")
        self.tree.heading("sanatci", text="Sanatçı")
        self.tree.heading("musteri", text="Telif Sahibi (Müşteri)")
        self.tree.heading("personel", text="Lisanslayan")
        self.tree.heading("tarih", text="İşlem Tarihi")
        self.tree.heading("durum", text="Durum")

        # Sütun Genişlikleri
        self.tree.column("seri_no", width=110)
        self.tree.column("dosya_adi", width=120)
        self.tree.column("eser_adi", width=140)
        self.tree.column("sanatci", width=120)
        self.tree.column("musteri", width=150)
        self.tree.column("personel", width=110)
        self.tree.column("tarih", width=110)
        self.tree.column("durum", width=80)

        # Dikey Scrollbar
        scrollbar = ttk.Scrollbar(table_frame, orient="vertical", command=self.tree.yview)
        scrollbar.grid(row=0, column=1, sticky="ns")
        self.tree.configure(yscrollcommand=scrollbar.set)

        # Veritabanından verileri tabloya doldur
        self.populate_history()

    def populate_history(self, search_query=None):
        for item in self.tree.get_children():
            self.tree.delete(item)

        # Veritabanından kayıtları çek
        db_records = gecmis_listesi_getir(search_query)

        # Tabloyu doldur
        for row in db_records:
            self.tree.insert("", tk.END, values=row)

    def filter_history(self):
        query = self.search_entry.get().strip()
        self.populate_history(query if query else None)

    def delete_selected_record(self):
        selected_item = self.tree.selection()
        if not selected_item:
            messagebox.showwarning("Seçim Eksik", "Lütfen silmek istediğiniz kaydı tablodan seçin!")
            return

        values = self.tree.item(selected_item, 'values')
        seri_no = values[0]
        
        soru = messagebox.askyesno("Kayıt Silme", f"{seri_no} seri numaralı kaydı silmek istediğinizden emin misiniz?\n(Bu işlem soft-delete olarak işaretlenecektir.)")
        if soru:
            ses_kaydi_soft_delete(seri_no)
            self.populate_history()
            messagebox.showinfo("Başarılı", f"{seri_no} seri numaralı kayıt veritabanında pasifleştirildi (Silindi).")

    # =========================================================================
    # SEKME 4: MÜŞTERİ YÖNETİMİ (CUSTOMER TAB - ADMIN & PROD ONLY)
    # =========================================================================
    def setup_customer_tab(self, tab):
        tab.grid_columnconfigure(0, weight=1, uniform="group_cust")
        tab.grid_columnconfigure(1, weight=1, uniform="group_cust")
        tab.grid_rowconfigure(0, weight=1)

        # Sol Panel: Müşteri Kayıt Formu
        form_frame = ctk.CTkFrame(tab)
        form_frame.grid(row=0, column=0, sticky="nsew", padx=(0, 10), pady=10)
        
        ctk.CTkLabel(form_frame, text="Yeni Telif Sahibi (Müşteri) Ekle", font=("Arial", 16, "bold"), text_color="#525fe1").pack(pady=15)

        # Form Alanları
        ctk.CTkLabel(form_frame, text="Müşteri Ünvanı / Adı:", font=("Arial", 12, "bold")).pack(anchor="w", padx=30, pady=(10, 2))
        self.new_cust_name_entry = ctk.CTkEntry(form_frame, placeholder_text="Firma ünvanı veya sanatçı adı...")
        self.new_cust_name_entry.pack(fill="x", padx=30, pady=2)

        ctk.CTkLabel(form_frame, text="İletişim E-postası:", font=("Arial", 12, "bold")).pack(anchor="w", padx=30, pady=(10, 2))
        self.new_cust_email_entry = ctk.CTkEntry(form_frame, placeholder_text="E-posta adresi...")
        self.new_cust_email_entry.pack(fill="x", padx=30, pady=2)

        ctk.CTkLabel(form_frame, text="Telefon Numarası:", font=("Arial", 12, "bold")).pack(anchor="w", padx=30, pady=(10, 2))
        self.new_cust_phone_entry = ctk.CTkEntry(form_frame, placeholder_text="Telefon numarası...")
        self.new_cust_phone_entry.pack(fill="x", padx=30, pady=2)

        # Kaydet Butonu
        btn_save_customer = ctk.CTkButton(
            form_frame, 
            text="Müşteriyi Kaydet", 
            fg_color="#525fe1", 
            hover_color="#3d49b8", 
            font=("Arial", 13, "bold"),
            height=35,
            command=self.save_new_customer
        )
        btn_save_customer.pack(fill="x", padx=30, pady=35)

        # Sağ Panel: Müşteri Listesi
        list_frame = ctk.CTkFrame(tab)
        list_frame.grid(row=0, column=1, sticky="nsew", padx=(10, 0), pady=10)
        list_frame.grid_columnconfigure(0, weight=1)
        list_frame.grid_rowconfigure(1, weight=1)

        ctk.CTkLabel(list_frame, text="Mevcut Müşteri Listesi", font=("Arial", 16, "bold")).grid(row=0, column=0, sticky="w", padx=15, pady=15)

        # Treeview Tablosu
        columns = ("unvan", "eposta", "telefon", "kayit_tarihi")
        self.customer_tree = ttk.Treeview(list_frame, columns=columns, show="headings")
        self.customer_tree.grid(row=1, column=0, sticky="nsew", padx=15, pady=(0, 15))

        self.customer_tree.heading("unvan", text="Müşteri Ünvanı / Adı")
        self.customer_tree.heading("eposta", text="E-posta")
        self.customer_tree.heading("telefon", text="Telefon")
        self.customer_tree.heading("kayit_tarihi", text="Kayıt Tarihi")

        self.customer_tree.column("unvan", width=140)
        self.customer_tree.column("eposta", width=120)
        self.customer_tree.column("telefon", width=100)
        self.customer_tree.column("kayit_tarihi", width=100)

        # Dikey Scrollbar
        scrollbar = ttk.Scrollbar(list_frame, orient="vertical", command=self.customer_tree.yview)
        scrollbar.grid(row=1, column=1, sticky="ns", pady=(0, 15))
        self.customer_tree.configure(yscrollcommand=scrollbar.set)

        self.populate_customer_list()

    def populate_customer_list(self):
        for item in self.customer_tree.get_children():
            self.customer_tree.delete(item)

        musteriler = musterileri_detayli_getir()
        for row in musteriler:
            self.customer_tree.insert("", tk.END, values=row)

    def save_new_customer(self):
        unvan = self.new_cust_name_entry.get().strip()
        eposta = self.new_cust_email_entry.get().strip()
        telefon = self.new_cust_phone_entry.get().strip()

        if not unvan or not eposta or not telefon:
            messagebox.showwarning("Eksik Bilgi", "Lütfen tüm müşteri bilgilerini doldurun!")
            return

        basarili = musteri_ekle(unvan, eposta, telefon)

        if basarili:
            messagebox.showinfo("Başarılı", f"Müşteri '{unvan}' başarıyla kaydedildi!")
            
            # Formu temizle
            self.new_cust_name_entry.delete(0, tk.END)
            self.new_cust_email_entry.delete(0, tk.END)
            self.new_cust_phone_entry.delete(0, tk.END)
            
            # Listeyi yenile
            self.populate_customer_list()
            
            # Lisanslama ekranındaki dropdown listesini yenile
            self.yenile_musteri_listesi()
            client_names = list(self.clients_map.keys())
            self.lic_client_combobox.configure(values=client_names)
            if client_names:
                self.lic_client_combobox.set(client_names[-1]) # En son ekleneni seç
        else:
            messagebox.showerror("Hata", f"Müşteri kaydedilirken bir veritabanı hatası oluştu!")

    # =========================================================================
    # SEKME 5: PERSONEL YÖNETİMİ (PERSONNEL TAB - ADMIN ONLY)
    # =========================================================================
    def setup_personnel_tab(self, tab):
        tab.grid_columnconfigure(0, weight=1, uniform="group2")
        tab.grid_columnconfigure(1, weight=1, uniform="group2")
        tab.grid_rowconfigure(0, weight=1)

        # Sol Panel: Personel Ekleme Formu
        form_frame = ctk.CTkFrame(tab)
        form_frame.grid(row=0, column=0, sticky="nsew", padx=(0, 10), pady=10)
        
        ctk.CTkLabel(form_frame, text="Yeni Personel Tanımla", font=("Arial", 16, "bold"), text_color="#525fe1").pack(pady=15)

        # Form Alanları
        ctk.CTkLabel(form_frame, text="Kullanıcı Adı:", font=("Arial", 12, "bold")).pack(anchor="w", padx=30, pady=(10, 2))
        self.new_username_entry = ctk.CTkEntry(form_frame, placeholder_text="Kullanıcı adı...")
        self.new_username_entry.pack(fill="x", padx=30, pady=2)

        ctk.CTkLabel(form_frame, text="Ad Soyad:", font=("Arial", 12, "bold")).pack(anchor="w", padx=30, pady=(10, 2))
        self.new_fullname_entry = ctk.CTkEntry(form_frame, placeholder_text="Personel adı soyadı...")
        self.new_fullname_entry.pack(fill="x", padx=30, pady=2)

        ctk.CTkLabel(form_frame, text="Şifre:", font=("Arial", 12, "bold")).pack(anchor="w", padx=30, pady=(10, 2))
        self.new_password_entry = ctk.CTkEntry(form_frame, placeholder_text="Giriş şifresi...", show="*")
        self.new_password_entry.pack(fill="x", padx=30, pady=2)

        # Dinamik Rol Seçimi
        ctk.CTkLabel(form_frame, text="Sistem Rolü:", font=("Arial", 12, "bold")).pack(anchor="w", padx=30, pady=(10, 2))
        
        roller = rolleri_getir()
        self.roles_map = {rol_adi: rid for rid, rol_adi in roller}
        role_names = list(self.roles_map.keys())

        self.new_role_combobox = ctk.CTkComboBox(form_frame, values=role_names)
        self.new_role_combobox.pack(fill="x", padx=30, pady=2)
        if role_names:
            self.new_role_combobox.set(role_names[0])

        # Kaydet Butonu
        btn_save_personnel = ctk.CTkButton(
            form_frame, 
            text="Personeli Kaydet", 
            fg_color="#525fe1", 
            hover_color="#3d49b8", 
            font=("Arial", 13, "bold"),
            height=35,
            command=self.save_new_personnel
        )
        btn_save_personnel.pack(fill="x", padx=30, pady=25)

        # Sağ Panel: Mevcut Personeller Listesi
        list_frame = ctk.CTkFrame(tab)
        list_frame.grid(row=0, column=1, sticky="nsew", padx=(10, 0), pady=10)
        list_frame.grid_columnconfigure(0, weight=1)
        list_frame.grid_rowconfigure(1, weight=1)

        ctk.CTkLabel(list_frame, text="Kayıtlı Personel Listesi", font=("Arial", 16, "bold")).grid(row=0, column=0, sticky="w", padx=15, pady=15)

        # Treeview Tablosu
        columns = ("kullanici_adi", "ad_soyad", "rol")
        self.personnel_tree = ttk.Treeview(list_frame, columns=columns, show="headings")
        self.personnel_tree.grid(row=1, column=0, sticky="nsew", padx=15, pady=(0, 15))

        self.personnel_tree.heading("kullanici_adi", text="Kullanıcı Adı")
        self.personnel_tree.heading("ad_soyad", text="Ad Soyad")
        self.personnel_tree.heading("rol", text="Sistem Rolü")

        self.personnel_tree.column("kullanici_adi", width=120)
        self.personnel_tree.column("ad_soyad", width=150)
        self.personnel_tree.column("rol", width=120)

        # Dikey Scrollbar
        scrollbar = ttk.Scrollbar(list_frame, orient="vertical", command=self.personnel_tree.yview)
        scrollbar.grid(row=1, column=1, sticky="ns", pady=(0, 15))
        self.personnel_tree.configure(yscrollcommand=scrollbar.set)

        self.populate_personnel_list()

    def populate_personnel_list(self):
        for item in self.personnel_tree.get_children():
            self.personnel_tree.delete(item)

        personeller = personelleri_getir()
        for row in personeller:
            self.personnel_tree.insert("", tk.END, values=row)

    def save_new_personnel(self):
        username = self.new_username_entry.get().strip()
        fullname = self.new_fullname_entry.get().strip()
        password = self.new_password_entry.get().strip()
        role_name = self.new_role_combobox.get()
        role_id = self.roles_map.get(role_name)

        if not username or not fullname or not password or not role_id:
            messagebox.showwarning("Eksik Bilgi", "Lütfen tüm personel bilgilerini doldurun!")
            return

        basarili = personel_ekle(username, password, fullname, role_id)

        if basarili:
            messagebox.showinfo("Başarılı", f"Personel '{fullname}' başarıyla kaydedildi!")
            self.new_username_entry.delete(0, tk.END)
            self.new_fullname_entry.delete(0, tk.END)
            self.new_password_entry.delete(0, tk.END)
            self.populate_personnel_list()
        else:
            messagebox.showerror("Hata", f"'{username}' kullanıcı adı sistemde zaten mevcut!")

if __name__ == "__main__":
    app = AudioCryptApp()
    app.mainloop()
