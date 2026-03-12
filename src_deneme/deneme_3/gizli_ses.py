import wave

# --- YAZMA (GİZLEME) FONKSİYONU ---
def mesaj_gizle(audio_yolu, mesaj, cikis_yolu):
    with wave.open(audio_yolu, 'rb') as ses:
        parametreler = ses.getparams()
        frame_verisi = bytearray(ses.readframes(ses.getnframes()))

    # Mesajı 0 ve 1'lere çeviriyoruz
    mesaj += "#####" # Bitiş işareti
    binary_mesaj = ''.join(format(ord(i), '08b') for i in mesaj)
    
    # Her ses hücresinin (frame) son bitine mesajın bitini yazıyoruz
    for i in range(len(binary_mesaj)):
        frame_verisi[i] = (frame_verisi[i] & 254) | int(binary_mesaj[i])

    # Yeni dosyayı oluşturuyoruz
    with wave.open(cikis_yolu, 'wb') as yeni_ses:
        yeni_ses.setparams(parametreler)
        yeni_ses.writeframes(frame_verisi)
    print(f"Mühürleme Başarılı! Yeni dosya: {cikis_yolu}")

# ÇALIŞTIRMA (YAZMA)
input_ses = "Ring01.wav"
output_ses = "gizli_ses_4.wav"
notum = "Bu bir gizli mesajdır!"

mesaj_gizle(input_ses, notum, output_ses)