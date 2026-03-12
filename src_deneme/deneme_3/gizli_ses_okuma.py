import wave

# --- OKUMA (ÇÖZME) FONKSİYONU ---
def mesaj_oku(audio_yolu):
    with wave.open(audio_yolu, 'rb') as ses:
        frame_verisi = bytearray(ses.readframes(ses.getnframes()))

    # Her hücrenin son bitini (LSB) topluyoruz
    binary_data = ""
    for i in range(len(frame_verisi)):
        binary_data += str(frame_verisi[i] & 1)

    # 0 ve 1'leri tekrar harfe dönüştürüyoruz
    mesaj = ""
    for i in range(0, len(binary_data), 8):
        byte = binary_data[i:i+8]
        karakter = chr(int(byte, 2))
        mesaj += karakter
        if "#####" in mesaj: # İşareti görünce dur
            break
            
    return mesaj.replace("#####", "")

# ÇALIŞTIRMA (OKUMA)
# DOSYANIN EN ALTINDA BUNLAR OLMALI:
okunacak_dosya = "gizli_ses_4.wav"
sonuc = mesaj_oku(okunacak_dosya)
print(f"Sesten Çıkarılan Gizli Mesaj: {sonuc}")
print("--- OKUMA İŞLEMİ BİTTİ ---")