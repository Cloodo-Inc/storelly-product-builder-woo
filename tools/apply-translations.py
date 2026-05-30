#!/usr/bin/env python3
"""Apply translation table to all locale .po files.

Run inside the wp_app container (msgfmt + python3 available):
    python3 tools/apply-translations.py

Parser handles: single-line msgid/msgstr, multi-line continuation,
msgctxt, and plural (msgid_plural / msgstr[N]). Plural entries are
left untouched (translation table is single-form only).
"""
import re
from pathlib import Path

LANG_DIR = Path("languages")
LOCALES = ["vi", "fr_FR", "de_DE", "es_ES", "pt_BR", "it_IT", "ja", "zh_CN", "ru_RU", "ar",
           "nl_NL", "pl_PL", "tr_TR", "sv_SE", "id_ID"]
TEXTDOMAIN = "storelly-product-builder-for-woocommerce"


# ------------------------- translation tables ------------------------------
CORE = {
    "Overview Dashboard": {
        "vi": "Tổng quan", "fr_FR": "Tableau de bord", "de_DE": "Übersicht",
        "es_ES": "Panel general", "pt_BR": "Painel geral", "it_IT": "Pannello di controllo",
        "ja": "概要ダッシュボード", "zh_CN": "概览仪表板", "ru_RU": "Сводная панель",
        "ar": "لوحة النظرة العامة",
    },
    "General Settings": {
        "vi": "Cài đặt chung", "fr_FR": "Paramètres généraux", "de_DE": "Allgemeine Einstellungen",
        "es_ES": "Configuración general", "pt_BR": "Configurações gerais", "it_IT": "Impostazioni generali",
        "ja": "一般設定", "zh_CN": "常规设置", "ru_RU": "Общие настройки", "ar": "الإعدادات العامة",
    },
    "Pricing Options": {
        "vi": "Tùy chọn giá", "fr_FR": "Options de prix", "de_DE": "Preisoptionen",
        "es_ES": "Opciones de precio", "pt_BR": "Opções de preço", "it_IT": "Opzioni di prezzo",
        "ja": "価格オプション", "zh_CN": "定价选项", "ru_RU": "Опции цены", "ar": "خيارات التسعير",
    },
    "Visual Builder": {
        "vi": "Trình dựng trực quan", "fr_FR": "Constructeur visuel", "de_DE": "Visueller Editor",
        "es_ES": "Constructor visual", "pt_BR": "Construtor visual", "it_IT": "Costruttore visivo",
        "ja": "ビジュアルビルダー", "zh_CN": "可视化生成器", "ru_RU": "Визуальный конструктор",
        "ar": "المنشئ المرئي",
    },
    "Linked Products": {
        "vi": "Sản phẩm liên kết", "fr_FR": "Produits liés", "de_DE": "Verknüpfte Produkte",
        "es_ES": "Productos vinculados", "pt_BR": "Produtos vinculados", "it_IT": "Prodotti collegati",
        "ja": "連携商品", "zh_CN": "关联产品", "ru_RU": "Связанные товары", "ar": "المنتجات المرتبطة",
    },
    "Custom Orders": {
        "vi": "Đơn tùy biến", "fr_FR": "Commandes personnalisées", "de_DE": "Benutzerdefinierte Bestellungen",
        "es_ES": "Pedidos personalizados", "pt_BR": "Pedidos personalizados", "it_IT": "Ordini personalizzati",
        "ja": "カスタム注文", "zh_CN": "定制订单", "ru_RU": "Заказы на заказ", "ar": "الطلبات المخصصة",
    },
    "Quote Requests": {
        "vi": "Yêu cầu báo giá", "fr_FR": "Demandes de devis", "de_DE": "Angebotsanfragen",
        "es_ES": "Solicitudes de cotización", "pt_BR": "Solicitações de orçamento", "it_IT": "Richieste di preventivo",
        "ja": "見積依頼", "zh_CN": "报价请求", "ru_RU": "Запросы котировок", "ar": "طلبات عروض الأسعار",
    },
    "Design Files": {
        "vi": "File thiết kế", "fr_FR": "Fichiers de design", "de_DE": "Designdateien",
        "es_ES": "Archivos de diseño", "pt_BR": "Arquivos de design", "it_IT": "File di design",
        "ja": "デザインファイル", "zh_CN": "设计文件", "ru_RU": "Файлы дизайна", "ar": "ملفات التصميم",
    },
    "License Plan": {
        "vi": "Gói giấy phép", "fr_FR": "Plan de licence", "de_DE": "Lizenzplan",
        "es_ES": "Plan de licencia", "pt_BR": "Plano de licença", "it_IT": "Piano di licenza",
        "ja": "ライセンスプラン", "zh_CN": "许可计划", "ru_RU": "Тарифный план", "ar": "خطة الترخيص",
    },
    "Custom Fonts": {
        "vi": "Font tùy biến", "fr_FR": "Polices personnalisées", "de_DE": "Benutzerdefinierte Schriften",
        "es_ES": "Fuentes personalizadas", "pt_BR": "Fontes personalizadas", "it_IT": "Font personalizzati",
        "ja": "カスタムフォント", "zh_CN": "自定义字体", "ru_RU": "Пользовательские шрифты",
        "ar": "الخطوط المخصصة",
    },
    "Setup Wizard": {
        "vi": "Cài đặt nhanh", "fr_FR": "Assistant de configuration", "de_DE": "Einrichtungsassistent",
        "es_ES": "Asistente de configuración", "pt_BR": "Assistente de configuração", "it_IT": "Procedura guidata",
        "ja": "セットアップウィザード", "zh_CN": "安装向导", "ru_RU": "Мастер настройки", "ar": "معالج الإعداد",
    },
    "Options Templates": {
        "vi": "Mẫu tùy chọn", "fr_FR": "Modèles d'options", "de_DE": "Optionsvorlagen",
        "es_ES": "Plantillas de opciones", "pt_BR": "Modelos de opções", "it_IT": "Modelli opzioni",
        "ja": "オプションテンプレート", "zh_CN": "选项模板", "ru_RU": "Шаблоны опций", "ar": "قوالب الخيارات",
    },
    "B2B Clients": {
        "vi": "Khách hàng B2B", "fr_FR": "Clients B2B", "de_DE": "B2B-Kunden",
        "es_ES": "Clientes B2B", "pt_BR": "Clientes B2B", "it_IT": "Clienti B2B",
        "ja": "B2Bクライアント", "zh_CN": "B2B 客户", "ru_RU": "B2B-клиенты", "ar": "عملاء B2B",
    },
    "About": {
        "vi": "Giới thiệu", "fr_FR": "À propos", "de_DE": "Über", "es_ES": "Acerca de",
        "pt_BR": "Sobre", "it_IT": "Informazioni", "ja": "製品情報", "zh_CN": "关于",
        "ru_RU": "О плагине", "ar": "حول",
    },
    "Coming soon.": {
        "vi": "Sắp ra mắt.", "fr_FR": "Bientôt disponible.", "de_DE": "Demnächst verfügbar.",
        "es_ES": "Próximamente.", "pt_BR": "Em breve.", "it_IT": "Prossimamente.",
        "ja": "近日公開。", "zh_CN": "即将推出。", "ru_RU": "Скоро.", "ar": "قريباً.",
    },
    "Go to Pricing Options": {
        "vi": "Đi tới Tùy chọn giá", "fr_FR": "Aller aux options de prix",
        "de_DE": "Zu Preisoptionen", "es_ES": "Ir a Opciones de precio",
        "pt_BR": "Ir para Opções de preço", "it_IT": "Vai alle Opzioni di prezzo",
        "ja": "価格オプションへ", "zh_CN": "前往定价选项", "ru_RU": "К опциям цены",
        "ar": "اذهب إلى خيارات التسعير",
    },
    "You do not have permission to access this page.": {
        "vi": "Bạn không có quyền truy cập trang này.",
        "fr_FR": "Vous n'avez pas la permission d'accéder à cette page.",
        "de_DE": "Sie haben keine Berechtigung, auf diese Seite zuzugreifen.",
        "es_ES": "No tienes permiso para acceder a esta página.",
        "pt_BR": "Você não tem permissão para acessar esta página.",
        "it_IT": "Non hai i permessi per accedere a questa pagina.",
        "ja": "このページにアクセスする権限がありません。",
        "zh_CN": "您没有权限访问此页面。",
        "ru_RU": "У вас нет прав доступа к этой странице.",
        "ar": "ليس لديك إذن للوصول إلى هذه الصفحة.",
    },
    "Security error.": {
        "vi": "Lỗi bảo mật.", "fr_FR": "Erreur de sécurité.", "de_DE": "Sicherheitsfehler.",
        "es_ES": "Error de seguridad.", "pt_BR": "Erro de segurança.", "it_IT": "Errore di sicurezza.",
        "ja": "セキュリティエラー。", "zh_CN": "安全错误。", "ru_RU": "Ошибка безопасности.",
        "ar": "خطأ في الأمان.",
    },
    "Save": {
        "vi": "Lưu", "fr_FR": "Enregistrer", "de_DE": "Speichern", "es_ES": "Guardar",
        "pt_BR": "Salvar", "it_IT": "Salva", "ja": "保存", "zh_CN": "保存",
        "ru_RU": "Сохранить", "ar": "حفظ",
    },
    "Cancel": {
        "vi": "Hủy", "fr_FR": "Annuler", "de_DE": "Abbrechen", "es_ES": "Cancelar",
        "pt_BR": "Cancelar", "it_IT": "Annulla", "ja": "キャンセル", "zh_CN": "取消",
        "ru_RU": "Отмена", "ar": "إلغاء",
    },
    "Delete": {
        "vi": "Xóa", "fr_FR": "Supprimer", "de_DE": "Löschen", "es_ES": "Eliminar",
        "pt_BR": "Excluir", "it_IT": "Elimina", "ja": "削除", "zh_CN": "删除",
        "ru_RU": "Удалить", "ar": "حذف",
    },
    "Edit": {
        "vi": "Sửa", "fr_FR": "Modifier", "de_DE": "Bearbeiten", "es_ES": "Editar",
        "pt_BR": "Editar", "it_IT": "Modifica", "ja": "編集", "zh_CN": "编辑",
        "ru_RU": "Изменить", "ar": "تعديل",
    },
    "Add": {
        "vi": "Thêm", "fr_FR": "Ajouter", "de_DE": "Hinzufügen", "es_ES": "Añadir",
        "pt_BR": "Adicionar", "it_IT": "Aggiungi", "ja": "追加", "zh_CN": "添加",
        "ru_RU": "Добавить", "ar": "إضافة",
    },
    "Update": {
        "vi": "Cập nhật", "fr_FR": "Mettre à jour", "de_DE": "Aktualisieren",
        "es_ES": "Actualizar", "pt_BR": "Atualizar", "it_IT": "Aggiorna",
        "ja": "更新", "zh_CN": "更新", "ru_RU": "Обновить", "ar": "تحديث",
    },
    "Close": {
        "vi": "Đóng", "fr_FR": "Fermer", "de_DE": "Schließen", "es_ES": "Cerrar",
        "pt_BR": "Fechar", "it_IT": "Chiudi", "ja": "閉じる", "zh_CN": "关闭",
        "ru_RU": "Закрыть", "ar": "إغلاق",
    },
    "Yes": {
        "vi": "Có", "fr_FR": "Oui", "de_DE": "Ja", "es_ES": "Sí",
        "pt_BR": "Sim", "it_IT": "Sì", "ja": "はい", "zh_CN": "是",
        "ru_RU": "Да", "ar": "نعم",
    },
    "No": {
        "vi": "Không", "fr_FR": "Non", "de_DE": "Nein", "es_ES": "No",
        "pt_BR": "Não", "it_IT": "No", "ja": "いいえ", "zh_CN": "否",
        "ru_RU": "Нет", "ar": "لا",
    },
    "Action": {
        "vi": "Hành động", "fr_FR": "Action", "de_DE": "Aktion", "es_ES": "Acción",
        "pt_BR": "Ação", "it_IT": "Azione", "ja": "操作", "zh_CN": "操作",
        "ru_RU": "Действие", "ar": "إجراء",
    },
    "Actions": {
        "vi": "Hành động", "fr_FR": "Actions", "de_DE": "Aktionen", "es_ES": "Acciones",
        "pt_BR": "Ações", "it_IT": "Azioni", "ja": "操作", "zh_CN": "操作",
        "ru_RU": "Действия", "ar": "الإجراءات",
    },
    "Settings": {
        "vi": "Cài đặt", "fr_FR": "Paramètres", "de_DE": "Einstellungen",
        "es_ES": "Ajustes", "pt_BR": "Configurações", "it_IT": "Impostazioni",
        "ja": "設定", "zh_CN": "设置", "ru_RU": "Настройки", "ar": "الإعدادات",
    },
    "Loading...": {
        "vi": "Đang tải...", "fr_FR": "Chargement...", "de_DE": "Wird geladen...",
        "es_ES": "Cargando...", "pt_BR": "Carregando...", "it_IT": "Caricamento...",
        "ja": "読み込み中...", "zh_CN": "加载中...", "ru_RU": "Загрузка...", "ar": "جار التحميل...",
    },
}

# ---- 5 extra popular WP locales (Dutch, Polish, Turkish, Swedish, Indonesian) ----
# Layout: locale -> {msgid: msgstr}. Same coverage as CORE.
EXTRA_BY_LOCALE = {
    "nl_NL": {
        "Overview Dashboard": "Overzichtsdashboard",
        "General Settings": "Algemene instellingen",
        "Pricing Options": "Prijsopties",
        "Visual Builder": "Visuele builder",
        "Linked Products": "Gekoppelde producten",
        "Custom Orders": "Aangepaste bestellingen",
        "Quote Requests": "Offerteaanvragen",
        "Design Files": "Ontwerpbestanden",
        "License Plan": "Licentieplan",
        "Custom Fonts": "Aangepaste lettertypes",
        "Setup Wizard": "Installatieassistent",
        "Options Templates": "Optiesjablonen",
        "B2B Clients": "B2B-klanten",
        "About": "Over",
        "Coming soon.": "Binnenkort beschikbaar.",
        "Go to Pricing Options": "Ga naar Prijsopties",
        "You do not have permission to access this page.": "Je hebt geen toestemming om deze pagina te bekijken.",
        "Security error.": "Beveiligingsfout.",
        "Save": "Opslaan", "Cancel": "Annuleren", "Delete": "Verwijderen",
        "Edit": "Bewerken", "Add": "Toevoegen", "Update": "Bijwerken",
        "Close": "Sluiten", "Yes": "Ja", "No": "Nee",
        "Action": "Actie", "Actions": "Acties", "Settings": "Instellingen",
        "Loading...": "Laden...",
    },
    "pl_PL": {
        "Overview Dashboard": "Pulpit przeglądowy",
        "General Settings": "Ustawienia ogólne",
        "Pricing Options": "Opcje cenowe",
        "Visual Builder": "Kreator wizualny",
        "Linked Products": "Powiązane produkty",
        "Custom Orders": "Zamówienia niestandardowe",
        "Quote Requests": "Zapytania ofertowe",
        "Design Files": "Pliki projektowe",
        "License Plan": "Plan licencyjny",
        "Custom Fonts": "Czcionki niestandardowe",
        "Setup Wizard": "Kreator konfiguracji",
        "Options Templates": "Szablony opcji",
        "B2B Clients": "Klienci B2B",
        "About": "O wtyczce",
        "Coming soon.": "Już wkrótce.",
        "Go to Pricing Options": "Przejdź do Opcji cenowych",
        "You do not have permission to access this page.": "Nie masz uprawnień do tej strony.",
        "Security error.": "Błąd bezpieczeństwa.",
        "Save": "Zapisz", "Cancel": "Anuluj", "Delete": "Usuń",
        "Edit": "Edytuj", "Add": "Dodaj", "Update": "Aktualizuj",
        "Close": "Zamknij", "Yes": "Tak", "No": "Nie",
        "Action": "Akcja", "Actions": "Działania", "Settings": "Ustawienia",
        "Loading...": "Ładowanie...",
    },
    "tr_TR": {
        "Overview Dashboard": "Genel bakış paneli",
        "General Settings": "Genel ayarlar",
        "Pricing Options": "Fiyatlandırma seçenekleri",
        "Visual Builder": "Görsel oluşturucu",
        "Linked Products": "Bağlantılı ürünler",
        "Custom Orders": "Özel siparişler",
        "Quote Requests": "Teklif istekleri",
        "Design Files": "Tasarım dosyaları",
        "License Plan": "Lisans planı",
        "Custom Fonts": "Özel fontlar",
        "Setup Wizard": "Kurulum sihirbazı",
        "Options Templates": "Seçenek şablonları",
        "B2B Clients": "B2B müşterileri",
        "About": "Hakkında",
        "Coming soon.": "Yakında.",
        "Go to Pricing Options": "Fiyatlandırma seçeneklerine git",
        "You do not have permission to access this page.": "Bu sayfaya erişim izniniz yok.",
        "Security error.": "Güvenlik hatası.",
        "Save": "Kaydet", "Cancel": "İptal", "Delete": "Sil",
        "Edit": "Düzenle", "Add": "Ekle", "Update": "Güncelle",
        "Close": "Kapat", "Yes": "Evet", "No": "Hayır",
        "Action": "Eylem", "Actions": "İşlemler", "Settings": "Ayarlar",
        "Loading...": "Yükleniyor...",
    },
    "sv_SE": {
        "Overview Dashboard": "Översiktspanel",
        "General Settings": "Allmänna inställningar",
        "Pricing Options": "Prisalternativ",
        "Visual Builder": "Visuell byggare",
        "Linked Products": "Länkade produkter",
        "Custom Orders": "Anpassade beställningar",
        "Quote Requests": "Offertförfrågningar",
        "Design Files": "Designfiler",
        "License Plan": "Licensplan",
        "Custom Fonts": "Anpassade typsnitt",
        "Setup Wizard": "Installationsguide",
        "Options Templates": "Alternativmallar",
        "B2B Clients": "B2B-kunder",
        "About": "Om",
        "Coming soon.": "Kommer snart.",
        "Go to Pricing Options": "Gå till Prisalternativ",
        "You do not have permission to access this page.": "Du har inte behörighet att komma åt den här sidan.",
        "Security error.": "Säkerhetsfel.",
        "Save": "Spara", "Cancel": "Avbryt", "Delete": "Radera",
        "Edit": "Redigera", "Add": "Lägg till", "Update": "Uppdatera",
        "Close": "Stäng", "Yes": "Ja", "No": "Nej",
        "Action": "Åtgärd", "Actions": "Åtgärder", "Settings": "Inställningar",
        "Loading...": "Laddar...",
    },
    "id_ID": {
        "Overview Dashboard": "Dasbor ringkasan",
        "General Settings": "Pengaturan umum",
        "Pricing Options": "Opsi harga",
        "Visual Builder": "Pembuat visual",
        "Linked Products": "Produk terhubung",
        "Custom Orders": "Pesanan khusus",
        "Quote Requests": "Permintaan penawaran",
        "Design Files": "Berkas desain",
        "License Plan": "Paket lisensi",
        "Custom Fonts": "Font khusus",
        "Setup Wizard": "Wisaya pengaturan",
        "Options Templates": "Templat opsi",
        "B2B Clients": "Klien B2B",
        "About": "Tentang",
        "Coming soon.": "Segera hadir.",
        "Go to Pricing Options": "Buka Opsi harga",
        "You do not have permission to access this page.": "Anda tidak memiliki izin untuk mengakses halaman ini.",
        "Security error.": "Kesalahan keamanan.",
        "Save": "Simpan", "Cancel": "Batal", "Delete": "Hapus",
        "Edit": "Edit", "Add": "Tambah", "Update": "Perbarui",
        "Close": "Tutup", "Yes": "Ya", "No": "Tidak",
        "Action": "Aksi", "Actions": "Tindakan", "Settings": "Pengaturan",
        "Loading...": "Memuat...",
    },
}

VI_EXTRA = {
    "You do not have permission.": "Bạn không có quyền.",
    "Invalid nonce.": "Nonce không hợp lệ.",
    "Invalid bulk nonce.": "Nonce hàng loạt không hợp lệ.",
    "Missing ID.": "Thiếu ID.",
    "Action failed. Please try again.": "Thao tác thất bại. Vui lòng thử lại.",
    "Action failed. Check the browser console for details.": "Thao tác thất bại. Xem console trình duyệt để biết chi tiết.",
    "Active": "Hoạt động", "Inactive": "Không hoạt động",
    "Draft": "Bản nháp", "Published": "Đã xuất bản", "Pending": "Chờ duyệt",
    "Title": "Tiêu đề", "Description": "Mô tả", "Name": "Tên",
    "Image": "Hình ảnh", "Price": "Giá", "Quantity": "Số lượng",
    "Type": "Loại", "Status": "Trạng thái", "Date": "Ngày",
    "Email": "Email", "Phone": "Điện thoại", "Address": "Địa chỉ",
    "City": "Thành phố", "Country": "Quốc gia",
    "Submit": "Gửi", "Reset": "Đặt lại", "Search": "Tìm kiếm",
    "Filter": "Lọc", "Sort": "Sắp xếp", "Apply": "Áp dụng",
    "Continue": "Tiếp tục", "Back": "Quay lại", "Next": "Tiếp", "Previous": "Trước",
    "Help": "Trợ giúp", "Documentation": "Tài liệu", "Support": "Hỗ trợ",
    "Free": "Miễn phí", "Pro": "Pro", "Upgrade": "Nâng cấp",
    "Activate": "Kích hoạt", "Deactivate": "Vô hiệu hóa",
    "Install": "Cài đặt", "Uninstall": "Gỡ bỏ",
    "Enable": "Bật", "Disable": "Tắt",
    "Show": "Hiện", "Hide": "Ẩn", "Open": "Mở",
    "Preview": "Xem trước", "Publish": "Xuất bản", "Unpublish": "Gỡ xuất bản",
    "Copy": "Sao chép", "Import": "Nhập", "Export": "Xuất",
    "Upload": "Tải lên", "Download": "Tải xuống",
    "File": "Tập tin", "Folder": "Thư mục", "Size": "Kích thước",
    "Color": "Màu", "Background": "Nền", "Font": "Phông", "Style": "Kiểu",
    "Position": "Vị trí", "Width": "Chiều rộng", "Height": "Chiều cao",
    "Align": "Căn", "Center": "Giữa", "Left": "Trái", "Right": "Phải",
    "Top": "Trên", "Bottom": "Dưới", "Layer": "Lớp",
    "Done": "Xong", "Error": "Lỗi", "Success": "Thành công",
    "Warning": "Cảnh báo", "Information": "Thông tin", "Confirm": "Xác nhận",
    "Required": "Bắt buộc", "Optional": "Tùy chọn",
    "All": "Tất cả", "None": "Không có",
    "Default": "Mặc định", "Custom": "Tùy chỉnh", "Other": "Khác",
    "Total": "Tổng", "Subtotal": "Tạm tính", "Tax": "Thuế",
    "Shipping": "Vận chuyển", "Discount": "Giảm giá",
    "Customer": "Khách hàng", "Order": "Đơn hàng", "Product": "Sản phẩm",
    "Variation": "Biến thể", "Category": "Danh mục", "Tag": "Thẻ",
    "Attribute": "Thuộc tính", "Option": "Tùy chọn",
    "Field": "Trường", "Group": "Nhóm", "Rule": "Quy tắc", "Condition": "Điều kiện",
    "Are you sure?": "Bạn có chắc chắn không?",
    "Saved.": "Đã lưu.", "Saved successfully.": "Đã lưu thành công.",
    "Failed to save.": "Lưu thất bại.", "No items found.": "Không tìm thấy mục nào.",
}


# ------------------------- PO file editor ----------------------------------
def escape_po(s: str) -> str:
    return s.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n")


def unescape_po(s: str) -> str:
    """Concatenate a sequence of quoted PO strings into raw value."""
    return (s.replace('\\"', '"')
             .replace("\\n", "\n")
             .replace("\\\\", "\\"))


_quoted = re.compile(r'^\s*"((?:[^"\\]|\\.)*)"\s*$')


def quoted_lines(lines, start):
    """From lines[start], collect consecutive quoted-only lines; return (joined_value, next_idx)."""
    parts = []
    i = start
    while i < len(lines):
        m = _quoted.match(lines[i])
        if not m:
            break
        parts.append(m.group(1))
        i += 1
    return unescape_po("".join(parts)), i


def patch_po(po_path: Path, table: dict) -> int:
    """Walk entries; if msgid in table and msgstr empty, replace with translation.
    Skip plural entries (msgid_plural present)."""
    src = po_path.read_text(encoding="utf-8").split("\n")
    out = []
    i = 0
    count = 0
    while i < len(src):
        line = src[i]
        m = re.match(r'^msgid\s+"((?:[^"\\]|\\.)*)"$', line)
        if not m:
            out.append(line)
            i += 1
            continue
        # Single-form msgid (no msgid_plural before). Collect msgid value.
        msgid_first = unescape_po(m.group(1))
        # Collect continuation
        cont, j = quoted_lines(src, i + 1)
        msgid_value = msgid_first + cont
        # Append msgid line + its continuation lines
        out.append(line)
        for k in range(i + 1, j):
            out.append(src[k])
        # Check what follows — could be msgid_plural OR msgstr
        if j < len(src) and src[j].startswith("msgid_plural"):
            # Plural — skip handling; just copy through until next blank line or next msgid.
            i = j
            continue
        if j < len(src) and src[j].startswith('msgstr "'):
            msgstr_line = src[j]
            m2 = re.match(r'^msgstr\s+"((?:[^"\\]|\\.)*)"$', msgstr_line)
            msgstr_first = unescape_po(m2.group(1)) if m2 else ""
            cont2, k = quoted_lines(src, j + 1)
            current_msgstr = msgstr_first + cont2
            if msgid_value and msgid_value in table and not current_msgstr:
                trans = table[msgid_value]
                out.append(f'msgstr "{escape_po(trans)}"')
                count += 1
            else:
                out.append(msgstr_line)
                for kk in range(j + 1, k):
                    out.append(src[kk])
            i = k
            continue
        # Unexpected — just move past msgid
        i = j
    po_path.write_text("\n".join(out), encoding="utf-8")
    return count


def main():
    for loc in LOCALES:
        po = LANG_DIR / f"{TEXTDOMAIN}-{loc}.po"
        if not po.exists():
            print(f"SKIP: {po}")
            continue
        table = {msgid: locs[loc] for msgid, locs in CORE.items() if loc in locs}
        if loc in EXTRA_BY_LOCALE:
            table.update(EXTRA_BY_LOCALE[loc])
        if loc == "vi":
            table.update(VI_EXTRA)
        n = patch_po(po, table)
        print(f"{loc}: filled {n} msgstrs in {po.name}")


if __name__ == "__main__":
    main()
