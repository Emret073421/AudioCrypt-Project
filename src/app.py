import os
import tkinter as tk
from tkinter import filedialog, messagebox, ttk
import customtkinter as ctk

# Veritabanı fonksiyonlarının içe aktarılması
from database import (
    dogrula_kullanici,
    musterileri_getir,
    gecmis_listesi_getir,
    ses_kaydi_soft_delete,
    ses_kaydi_bul_seri,
    rolleri_getir,
    personel_ekle,
    personelleri_getir
)

# CustomTkinter Tema ve Renk Paleti Ayarları
ctk.set_appearance_mode("Dark")
ctk.set_default_color_theme("blue")  # Modern mavi tema

class AudioCryptApp(ctk.CTk):
    def __init__(self):
        super().__init__()

        # Pencere Boyut ve Başlık Ayarları
        self.title("AudioCrypt Enterprise - Dijital Hak Yönetimi & Telif Koruma")
        self.geometry("1024x680")
        self.minsize(900, 600)

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

        # Veritabanından kullanıcıyı doğrula
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
        musteriler = musterileri_getir()
        self.clients_map = {unvan: cid for cid, unvan in musteriler}

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

        version_label = ctk.CTkLabel(sidebar, text="Enterprise v1.2", font=("Arial", 11, "italic"), text_color="gray")
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

        self.tabview.add("Otomasyon Paneli")
        self.tabview.add("Tarayıcı (Scanner)")
        self.tabview.add("İşlem Geçmişi")
        
        # Eğer kullanıcı admin ise "Personel Yönetimi" sekmesini ekle
        if self.current_permissions.get("admin_izni") == 1:
            self.tabview.add("Personel Yönetimi")

        # Sekmeleri Yapılandır
        self.setup_automation_tab(self.tabview.tab("Otomasyon Paneli"))
        self.setup_scanner_tab(self.tabview.tab("Tarayıcı (Scanner)"))
        self.setup_history_tab(self.tabview.tab("İşlem Geçmişi"))
        
        if self.current_permissions.get("admin_izni") == 1:
            self.setup_personnel_tab(self.tabview.tab("Personel Yönetimi"))

    def handle_logout(self):
        self.current_user_id = None
        self.current_user = None
        self.current_role = None
        self.current_permissions = {}
        self.clients_map = {}
        self.roles_map = {}
        if self.main_frame:
            self.main_frame.pack_forget()
        self.show_login_screen()

    # =========================================================================
    # SEKME 1: OTOMASYON PANELİ (AUTOMATION TAB)
    # =========================================================================
    def setup_automation_tab(self, tab):
        tab.grid_columnconfigure(0, weight=1, uniform="group1")
        tab.grid_columnconfigure(1, weight=1, uniform="group1")
        tab.grid_rowconfigure(0, weight=1)

        # Sol Panel: Ayarlar
        config_frame = ctk.CTkScrollableFrame(tab, label_text="Otomasyon Ayarları", label_font=("Arial", 14, "bold"))
        config_frame.grid(row=0, column=0, sticky="nsew", padx=(0, 10), pady=10)

        # Giriş Klasörü
        ctk.CTkLabel(config_frame, text="Giriş Klasörü (Watch Folder):", font=("Arial", 12, "bold")).pack(anchor="w", pady=(10, 2))
        input_row = ctk.CTkFrame(config_frame, fg_color="transparent")
        input_row.pack(fill="x", pady=2)
        self.input_dir_entry = ctk.CTkEntry(input_row, placeholder_text="Henüz seçilmedi...")
        self.input_dir_entry.pack(side="left", fill="x", expand=True, padx=(0, 5))
        
        # Varsayılan Giriş Klasörünü Ekle
        self.input_dir_entry.insert(0, self.default_input_dir)

        self.btn_select_input = ctk.CTkButton(input_row, text="Gözat", width=70, command=self.select_input_dir)
        self.btn_select_input.pack(side="right")

        # Çıkış Klasörü
        ctk.CTkLabel(config_frame, text="Çıkış Klasörü (Output Folder):", font=("Arial", 12, "bold")).pack(anchor="w", pady=(15, 2))
        output_row = ctk.CTkFrame(config_frame, fg_color="transparent")
        output_row.pack(fill="x", pady=2)
        self.output_dir_entry = ctk.CTkEntry(output_row, placeholder_text="Henüz seçilmedi...")
        self.output_dir_entry.pack(side="left", fill="x", expand=True, padx=(0, 5))
        
        # Varsayılan Çıkış Klasörünü Ekle
        self.output_dir_entry.insert(0, self.default_output_dir)

        self.btn_select_output = ctk.CTkButton(output_row, text="Gözat", width=70, command=self.select_output_dir)
        self.btn_select_output.pack(side="right")

        # Müşteri Seçimi (Dinamik)
        ctk.CTkLabel(config_frame, text="Telif Sahibi (Müşteri):", font=("Arial", 12, "bold")).pack(anchor="w", pady=(15, 2))
        client_names = list(self.clients_map.keys())
        self.client_combobox = ctk.CTkComboBox(config_frame, values=client_names)
        self.client_combobox.pack(fill="x", pady=2)
        if client_names:
            self.client_combobox.set(client_names[0])

        # Başlat/Durdur Butonları
        self.automation_status = False
        self.btn_toggle_automation = ctk.CTkButton(
            config_frame, 
            text="Otomasyonu Başlat", 
            fg_color="#2fa572", 
            hover_color="#248259", 
            font=("Arial", 14, "bold"),
            height=45,
            command=self.toggle_automation
        )
        self.btn_toggle_automation.pack(fill="x", pady=40)

        # Rol Yetki Kontrolü (RBAC)
        if self.current_permissions.get("yazma_izni") != 1:
            self.btn_toggle_automation.configure(state="disabled", fg_color="gray30", text="Otomasyon Yetkiniz Yok")
            self.btn_select_input.configure(state="disabled")
            self.btn_select_output.configure(state="disabled")
            self.client_combobox.configure(state="disabled")

        # Sağ Panel: Canlı Log / Durum Ekranı
        log_frame = ctk.CTkFrame(tab)
        log_frame.grid(row=0, column=1, sticky="nsew", padx=(10, 0), pady=10)
        log_frame.grid_rowconfigure(1, weight=1)
        log_frame.grid_columnconfigure(0, weight=1)

        ctk.CTkLabel(log_frame, text="Otomasyon Canlı İzleme Günlüğü", font=("Arial", 14, "bold")).grid(row=0, column=0, sticky="w", padx=15, pady=10)
        
        self.log_textbox = ctk.CTkTextbox(log_frame, font=("Courier", 12), state="disabled")
        self.log_textbox.grid(row=1, column=0, sticky="nsew", padx=15, pady=(0, 15))
        
        self.append_log("[SİSTEM] Varsayılan girdi/çıktı klasörleri hazırlandı.")

    def select_input_dir(self):
        path = filedialog.askdirectory()
        if path:
            self.input_dir_entry.delete(0, tk.END)
            self.input_dir_entry.insert(0, path)
            self.append_log(f"[SİSTEM] Giriş klasörü güncellendi: {path}")

    def select_output_dir(self):
        path = filedialog.askdirectory()
        if path:
            self.output_dir_entry.delete(0, tk.END)
            self.output_dir_entry.insert(0, path)
            self.append_log(f"[SİSTEM] Çıkış klasörü güncellendi: {path}")

    def toggle_automation(self):
        if not self.input_dir_entry.get() or not self.output_dir_entry.get():
            messagebox.showwarning("Eksik Ayar", "Lütfen önce Giriş ve Çıkış klasörlerini seçin!")
            return

        selected_client_name = self.client_combobox.get()
        selected_client_id = self.clients_map.get(selected_client_name)

        if not selected_client_id:
            messagebox.showwarning("Eksik Ayar", "Geçerli bir telif sahibi seçin!")
            return

        self.automation_status = not self.automation_status
        if self.automation_status:
            self.btn_toggle_automation.configure(text="Otomasyonu Durdur", fg_color="#e15252", hover_color="#b83d3d")
            self.append_log(f"[BAŞLADI] Klasör izleme başladı. Müşteri: {selected_client_name}")
            # Gelecek adımda Watchdog modülü buraya bağlanacak
        else:
            self.btn_toggle_automation.configure(text="Otomasyonu Başlat", fg_color="#2fa572", hover_color="#248259")
            self.append_log("[DURDURULDU] Otomasyon durduruldu.")

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
            text="Analiz Et ve Sorgula", 
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

        # Filigran Çözücü Bağlanana Kadar Mock Okuma Yapıyoruz.
        # Gelecekte burası: extracted_serial = extract_watermark(file_path) olacak.
        # Şimdilik örnek veritabanı sorgusunu göstermek için 'AC-98F4-2026' seri numarasını simüle ediyoruz.
        simulated_serial = "AC-98F4-2026"
        
        # Veritabanında seri numarasını ara
        db_record = ses_kaydi_bul_seri(simulated_serial)

        if db_record:
            # 1. Başlık
            title = ctk.CTkLabel(self.info_panel, text="🛡️ Telif Kartı ve Sahiplik Bilgisi", font=("Arial", 18, "bold"), text_color="#2fa572")
            title.pack(pady=15)

            # 2. Bilgi Grid
            grid_frame = ctk.CTkFrame(self.info_panel, fg_color="#1e1e1e", corner_radius=10)
            grid_frame.pack(fill="x", padx=40, pady=10)

            labels = [
                ("Seri Numarası (Watermark ID):", db_record["seri_no"]),
                ("Orijinal Dosya Adı:", db_record["orijinal_dosya"]),
                ("Telif Sahibi (Müşteri):", db_record["musteri_adi"]),
                ("İşlemi Yapan Personel:", db_record["personel_adi"]),
                ("İşlem Tarihi:", db_record["islem_tarihi"]),
                ("Durum:", db_record["durum"])
            ]

            for i, (key, value) in enumerate(labels):
                row = ctk.CTkFrame(grid_frame, fg_color="transparent")
                row.pack(fill="x", padx=15, pady=8)
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

        self.search_entry = ctk.CTkEntry(top_frame, placeholder_text="Seri No, Dosya Adı veya Müşteri ile ara...", width=350)
        self.search_entry.pack(side="left", padx=5)

        btn_search = ctk.CTkButton(top_frame, text="Filtrele", width=80, command=self.filter_history)
        btn_search.pack(side="left", padx=5)

        # Sağ üst: Silme (Soft-delete) butonu
        self.btn_delete_record = ctk.CTkButton(
            top_frame, 
            text="Seçili Kaydı Sil (Soft-Delete)", 
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

        columns = ("seri_no", "dosya_adi", "musteri", "personel", "tarih", "durum")
        self.tree = ttk.Treeview(table_frame, columns=columns, show="headings")
        self.tree.grid(row=0, column=0, sticky="nsew")

        # Sütun Başlıkları
        self.tree.heading("seri_no", text="Seri Numarası")
        self.tree.heading("dosya_adi", text="Dosya Adı")
        self.tree.heading("musteri", text="Telif Sahibi (Müşteri)")
        self.tree.heading("personel", text="İşlemi Yapan")
        self.tree.heading("tarih", text="İşlem Tarihi")
        self.tree.heading("durum", text="Durum")

        # Sütun Genişlikleri
        self.tree.column("seri_no", width=120)
        self.tree.column("dosya_adi", width=180)
        self.tree.column("musteri", width=150)
        self.tree.column("personel", width=120)
        self.tree.column("tarih", width=120)
        self.tree.column("durum", width=100)

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
    # SEKME 4: PERSONEL YÖNETİMİ (PERSONNEL TAB - ADMIN ONLY)
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
        
        # Veritabanından rolleri çek
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

        # Personelleri veritabanından çek ve listele
        self.populate_personnel_list()

    def populate_personnel_list(self):
        # Listeyi temizle
        for item in self.personnel_tree.get_children():
            self.personnel_tree.delete(item)

        # Veritabanından personelleri al
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

        # Veritabanına kaydet
        basarili = personel_ekle(username, password, fullname, role_id)

        if basarili:
            messagebox.showinfo("Başarılı", f"Personel '{fullname}' başarıyla kaydedildi!")
            # Formu temizle
            self.new_username_entry.delete(0, tk.END)
            self.new_fullname_entry.delete(0, tk.END)
            self.new_password_entry.delete(0, tk.END)
            # Listeyi yenile
            self.populate_personnel_list()
        else:
            messagebox.showerror("Hata", f"'{username}' kullanıcı adı sistemde zaten mevcut!")

if __name__ == "__main__":
    app = AudioCryptApp()
    app.mainloop()
