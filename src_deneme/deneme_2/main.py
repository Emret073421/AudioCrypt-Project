import customtkinter as ctk
from tkinter import filedialog, messagebox
import wave # Ses dosyasını açmak için
import os   # Dosya yolları ve boyutları için

class AudioCryptApp(ctk.CTk):
    def __init__(self):
        super().__init__()

        # Pencere Ayarları
        self.title("AudioCrypt v1.0 - AI Copyright Protection")
        self.geometry("500x550")
        
        # 1. Başlık ve Vizyon Etiketi
        self.label = ctk.CTkLabel(self, text="AudioCrypt: Şifreli Kaşe Sistemi", font=("Arial", 20, "bold"))
        self.label.pack(pady=(20, 5))
        
        self.sub_label = ctk.CTkLabel(self, text="Yapay Zeka Korumalı Steganografi", font=("Arial", 12, "italic"), text_color="gray")
        self.sub_label.pack(pady=(0, 20))

        # 2. Dosya Seçme Bölümü
        self.btn_file = ctk.CTkButton(self, text="Müzik Dosyası Seç (.wav)", command=self.file_picker, fg_color="#3b8ed0")
        self.btn_file.pack(pady=10)
        
        # Seçilen dosyanın adını gösterecek etiket
        self.file_info = ctk.CTkLabel(self, text="Henüz dosya seçilmedi", font=("Arial", 11))
        self.file_info.pack(pady=5)

        # 3. Gizli Kaşe Mesajı (Telif Bilgisi)
        self.entry_msg = ctk.CTkEntry(self, placeholder_text="Gömülecek Telif/Kaşe Bilgisi", width=350)
        self.entry_msg.pack(pady=10)

        # 4. Şifreleme Anahtarı (Parola)
        self.entry_pass = ctk.CTkEntry(self, placeholder_text="Güvenlik Anahtarı (Parola)", show="*", width=350)
        self.entry_pass.pack(pady=10)

        # 5. İşlem Butonu
        self.btn_run = ctk.CTkButton(self, text="Şifreli Kaşeyi Sese Mühürle", fg_color="#2fa572", command=self.run_process)
        self.btn_run.pack(pady=30)

    # --- Fonksiyonlar (Mantık Katmanı) ---

    def file_picker(self):
        # W3Schools'ta göreceğin dosya işleme mantığı
        dosya_yolu = filedialog.askopenfilename(filetypes=[("Ses Dosyası", "*.wav")])
        
        if dosya_yolu:
            self.secilen_dosya = dosya_yolu
            dosya_adi = os.path.basename(dosya_yolu)
            self.file_info.configure(text=f"Seçilen: {dosya_adi}", text_color="green")
            print(f"Başarılı: {dosya_adi} yüklendi.")

    def run_process(self):
        # Hata Yönetimi (Try/Except) kullanımı
        if not hasattr(self, 'secilen_dosya'):
            messagebox.showwarning("Uyarı", "Lütfen önce kaşelenecek ses dosyasını seçin!")
            return

        mesaj = self.entry_msg.get()
        parola = self.entry_pass.get()

        if not mesaj or not parola:
            messagebox.showerror("Eksik Bilgi", "Lütfen hem kaşe mesajını hem de parolayı girin!")
            return

        try:
            # wave kütüphanesi ile dosya analizi (Kütüphane Kullanımı)
            with wave.open(self.secilen_dosya, "rb") as ses:
                parametreler = ses.getparams()
                kapasite = ses.getnframes()
                
                # Terminale teknik bilgi basıyoruz (Debug/Öğrenme için)
                print(f"--- Teknik Analiz ---")
                print(f"Kapasite: {kapasite} frame (bit)")
                print(f"Kanal Sayısı: {parametreler.nchannels}")
                
                # Kapasite Kontrolü Mantığı
                if len(mesaj) * 8 > kapasite:
                    messagebox.showerror("Hata", "Ses dosyası bu kadar uzun bir mesajı gizlemek için çok kısa!")
                else:
                    # Şimdilik simülasyon yapıyoruz, bir sonraki adımda şifreleme kodunu buraya koyacağız
                    messagebox.showinfo("İşlem Başarılı", 
                        f"'{mesaj}' mesajı şifrelendi ve dijital kaşe olarak sesin içine mühürlendi!")
                    print("İşlem başarıyla simüle edildi.")

        except Exception as e:
            messagebox.showerror("Sistem Hatası", f"Beklenmedik bir hata oluştu: {e}")

if __name__ == "__main__":
    app = AudioCryptApp()
    app.mainloop()