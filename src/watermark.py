import json
import wave
from datetime import datetime


MAGIC = b"ACJSON1\0"
IMAGE_MAGIC = b"ACIMGJSON1\0"
LENGTH_SIZE = 4


class WatermarkCapacityError(ValueError):
    pass


class WatermarkReadError(ValueError):
    pass


def build_watermark_payload(
    seri_no,
    eser_adi,
    sanatci,
    telif_sahibi,
    lisanslayan,
    orijinal_dosya,
    dosya_hash,
    medya_turu="audio",
    ek_bilgiler=None,
):
    payload = {
        "schema": "AudioCryptWatermark",
        "version": 1,
        "medya_turu": medya_turu,
        "seri_no": seri_no,
        "eser_adi": eser_adi,
        "sanatci": sanatci,
        "telif_sahibi": telif_sahibi,
        "lisanslayan": lisanslayan,
        "orijinal_dosya": orijinal_dosya,
        "dosya_hash": dosya_hash,
        "olusturma_tarihi": datetime.now().strftime("%d.%m.%Y %H:%M"),
    }

    if ek_bilgiler:
        payload.update(ek_bilgiler)

    return payload


def payload_to_json(payload):
    return json.dumps(payload, ensure_ascii=False, indent=2)


def _bytes_to_bits(data):
    for byte in data:
        for shift in range(7, -1, -1):
            yield (byte >> shift) & 1


def _bits_to_bytes(bits):
    values = []
    for index in range(0, len(bits), 8):
        byte_bits = bits[index:index + 8]
        if len(byte_bits) < 8:
            break

        value = 0
        for bit in byte_bits:
            value = (value << 1) | bit
        values.append(value)
    return bytes(values)


def embed_json_watermark(input_wav, output_wav, payload):
    payload_bytes = json.dumps(
        payload,
        ensure_ascii=False,
        sort_keys=True,
        separators=(",", ":"),
    ).encode("utf-8")

    envelope = MAGIC + len(payload_bytes).to_bytes(LENGTH_SIZE, "big") + payload_bytes
    envelope_bits = list(_bytes_to_bits(envelope))

    with wave.open(input_wav, "rb") as source:
        params = source.getparams()
        frames = bytearray(source.readframes(source.getnframes()))

    if len(envelope_bits) > len(frames):
        max_payload_bytes = max((len(frames) // 8) - len(MAGIC) - LENGTH_SIZE, 0)
        raise WatermarkCapacityError(
            f"Ses dosyası bu JSON mühür için küçük. Maksimum yaklaşık {max_payload_bytes} byte veri sığar."
        )

    for index, bit in enumerate(envelope_bits):
        frames[index] = (frames[index] & 0xFE) | bit

    with wave.open(output_wav, "wb") as target:
        target.setparams(params)
        target.writeframes(frames)

    return {
        "payload_bytes": len(payload_bytes),
        "used_audio_bytes": len(envelope_bits),
        "capacity_audio_bytes": len(frames),
    }


def extract_json_watermark(audio_wav):
    with wave.open(audio_wav, "rb") as source:
        frames = bytearray(source.readframes(source.getnframes()))

    header_bit_count = (len(MAGIC) + LENGTH_SIZE) * 8
    if len(frames) < header_bit_count:
        raise WatermarkReadError("Ses dosyası mühür başlığı için çok küçük.")

    header_bits = [frames[index] & 1 for index in range(header_bit_count)]
    header = _bits_to_bytes(header_bits)

    if not header.startswith(MAGIC):
        return None

    payload_size = int.from_bytes(header[len(MAGIC):len(MAGIC) + LENGTH_SIZE], "big")
    payload_bit_count = payload_size * 8
    payload_start = header_bit_count
    payload_end = payload_start + payload_bit_count

    if payload_size <= 0 or payload_end > len(frames):
        raise WatermarkReadError("Mühür uzunluğu geçersiz veya ses dosyası eksik.")

    payload_bits = [frames[index] & 1 for index in range(payload_start, payload_end)]
    payload_bytes = _bits_to_bytes(payload_bits)

    try:
        payload = json.loads(payload_bytes.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError) as exc:
        raise WatermarkReadError(f"JSON mühür okunamadı: {exc}") from exc

    if payload.get("schema") != "AudioCryptWatermark":
        raise WatermarkReadError("Bulunan JSON AudioCrypt mühürü değil.")

    return payload


def embed_image_json_watermark(input_image, output_image, payload):
    payload_bytes = json.dumps(
        payload,
        ensure_ascii=False,
        sort_keys=True,
        separators=(",", ":"),
    ).encode("utf-8")

    with open(input_image, "rb") as source:
        image_bytes = source.read()

    envelope = IMAGE_MAGIC + len(payload_bytes).to_bytes(LENGTH_SIZE, "big") + payload_bytes

    with open(output_image, "wb") as target:
        target.write(image_bytes)
        target.write(envelope)

    return {
        "payload_bytes": len(payload_bytes),
        "original_file_bytes": len(image_bytes),
        "output_file_bytes": len(image_bytes) + len(envelope),
    }


def extract_image_json_watermark(image_file):
    with open(image_file, "rb") as source:
        data = source.read()

    marker_index = data.rfind(IMAGE_MAGIC)
    if marker_index == -1:
        return None

    size_start = marker_index + len(IMAGE_MAGIC)
    size_end = size_start + LENGTH_SIZE
    if size_end > len(data):
        raise WatermarkReadError("Görsel mühür başlığı eksik.")

    payload_size = int.from_bytes(data[size_start:size_end], "big")
    payload_start = size_end
    payload_end = payload_start + payload_size

    if payload_size <= 0 or payload_end > len(data):
        raise WatermarkReadError("Görsel mühür uzunluğu geçersiz veya dosya eksik.")

    payload_bytes = data[payload_start:payload_end]

    try:
        payload = json.loads(payload_bytes.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError) as exc:
        raise WatermarkReadError(f"Görsel JSON mühür okunamadı: {exc}") from exc

    if payload.get("schema") != "AudioCryptWatermark":
        raise WatermarkReadError("Bulunan JSON AudioCrypt mühürü değil.")

    return payload
