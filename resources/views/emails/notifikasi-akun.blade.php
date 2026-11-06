<!DOCTYPE html>
<html lang="id">
<body style="margin:0;padding:24px;background:#F3F5F8;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">
    <div style="max-width:460px;margin:0 auto;background:#ffffff;border-radius:10px;padding:28px;border:1px solid #E5E9EF;">
        <p style="margin:0 0 4px;font-size:13px;color:#6b7785;">SCP SMK Negeri 1 Bantul</p>
        <h2 style="margin:0 0 16px;font-size:18px;color:#1B4B7A;">{{ $judul }}</h2>

        <p style="margin:0 0 12px;font-size:14px;line-height:1.5;">Halo {{ $nama }},</p>
        @foreach($baris as $paragraf)
            <p style="margin:0 0 12px;font-size:14px;line-height:1.5;">{{ $paragraf }}</p>
        @endforeach
    </div>
</body>
</html>
