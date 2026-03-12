import customtkinter as ctk

class AudioCryptApp(ctk.CTk):
    def __init__(self):
        super().__init__()

        # Pencere Ayarları
        self.title("AudioCrypt v1.0")
        self.geometry("450x400")
        
        # 1. Başlık
        self.label = ctk.CTkLabel(self, text="Audio Steganography", font=("Arial", 24, "bold"))
        self.label.pack(pady=20)

        # 2. Dosya Seçme Butonu
        self.btn_file = ctk.CTkButton(self, text="Ses Dosyası Seç (.wav)", command=self.file_picker)
        self.btn_file.pack(pady=10)

        # 3. Mesaj Giriş Alanı
        self.entry_msg = ctk.CTkEntry(self, placeholder_text="Gizli Mesajı Yazın", width=300)
        self.entry_msg.pack(pady=10)

        # 4. Şifre (Password) Giriş Alanı -> İnovasyon burada!
        self.entry_pass = ctk.CTkEntry(self, placeholder_text="Şifreleme Anahtarı (Parola)", show="*", width=300)
        self.entry_pass.pack(pady=10)

        # 5. İşlem Butonu
        self.btn_run = ctk.CTkButton(self, text="Şifrele ve Sese Göm", fg_color="green", command=self.run_process)
        self.btn_run.pack(pady=20)

    def file_picker(self):
        print("Dosya seçme ekranı açılacak...")

    def run_process(self):
        print("Şifreleme işlemi başlıyor...")

if __name__ == "__main__":
    app = AudioCryptApp()
    app.mainloop()