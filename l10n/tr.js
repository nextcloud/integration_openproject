OC.L10N.register(
    "integration_openproject",
    {
    "OpenProject" : "OpenProject",
    "Invalid key" : "Anahtar geçersiz",
    "Error getting OAuth access token" : "OAuth erişim kodu alınırken sorun çıktı",
    "Error getting OAuth refresh token" : "OAuth yenileme kodu alınırken sorun çıktı.",
    "Error during OAuth exchanges" : "OAuth takasında sorun çıktı",
    "Direct download error" : "Doğrudan indirme sorunu",
    "This direct download link is invalid or has expired" : "Bu doğrudan indirme bağlantısı geçersiz ya da geçerlilik süresi dolmuş",
    "Bad HTTP method" : "HTTP yöntemi hatalı",
    "OAuth access token refused" : "OAuth erişim kodu reddedildi",
    "OpenProject Integration" : "OpenProject bütünleştirmesi",
    "Link Nextcloud files to OpenProject work packages" : "Nextcloud dosyaları ile OpenProject çalışma paketlerini ilişkilendirir",
    "This application enables seamless integration with open source project management and collaboration software OpenProject.\n\nOn the Nextcloud end, it allows users to:\n\n* Link files and folders with work packages in OpenProject\n* Find all work packages linked to a file or a folder\n* View OpenProject notifications via the dashboard\n* Search for work packages using Nextcloud's search bar\n\nOn the OpenProject end, users are able to:\n\n* View all Nextcloud files and folders linked to a work package\n* Download linked files or open them in Nextcloud to edit them\n\nFor more information on how to set up and use the OpenProject application, please refer to [integration setup guide](https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/) for administrators and [the user guide](https://www.openproject.org/docs/user-guide/nextcloud-integration/)." : "Bu uygulama, açık kaynaklı proje yönetimi ve işbirliği yazılımı OpenProject ile tam bütünleştirme sağlar. \n\nNextcloud tarafında, kullanıcılar şu işlemleri yapabilir:\n\n* Dosyaları ve klasörleri OpenProject çalışma paketleriyle ilişkilendirebilir\n* Bir dosya veya klasöre bağlı tüm iş paketlerini bulabilir\n* Pano üzerinden iş paketlerindeki güncellemeleri izleyebilir\n* Nextcloud arama çubuğunu kullanarak ilgili iş paketlerini bulabilir\n\nOpenProject tarafında, kullanıcılar şunları yapabilir:\n\n* Bir çalışma paketiyle ilişkilendirilmiş tüm Nextcloud dosyalarını görüntüleyebilir\n* Bağlantılı dosyaları indirebilir ya da düzenlemek için Nextcloud üzerinde açabilir\n\nOpenProject uygulamasının nasıl kurulacağı ve kullanılacağı hakkında ayrıntılı bilgi almak için yöneticiler için [bütünleştirme kurulum rehberi](https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/) ve [kullanıcı rehberi](https://www.openproject.org/docs/user-guide/nextcloud-integration/) bölümlerine bakabilirsiniz.",
    "OpenProject server" : "OpenProject sunucusu",
    "OpenProject host" : "OpenProject sunucu adı",
    "Please introduce your OpenProject host name" : "Lütfen OpenProject sunucu adını yazın",
    "Edit server information" : "Sunucu bilgilerini düzenle",
    "Cancel" : "İptal",
    "Save" : "Kaydet",
    "OpenProject OAuth settings" : "OpenProject OAuth ayarları",
    "Replace OpenProject OAuth values" : "OpenProject OAuth değerlerini değiştir",
    "Nextcloud OAuth client" : "Nextcloud OAuth istemcisi",
    "Yes, I have copied these values" : "Evet, bu değerleri kopyaladım",
    "Replace Nextcloud OAuth values" : "Nextcloud OAuth değerlerini değiştir",
    "Reset" : "Sıfırla",
    "Default user settings" : "Varsayılan kullanıcı ayarları",
    "A new user will receive these defaults and they will be applied to the integration app till the user changes them." : "Yeni bir kullanıcı bu varsayılan ayarları alır ve kullanıcı bunları değiştirene kadar bütünleştirme uygulamasına uygulanır.",
    "Enable navigation link" : "Gezinme bağlantısı kullanılsın",
    "Enable unified search for tickets" : "Destek kayıtları için birleşik arama yapılabilsin",
    "Administration > File storages" : "Yönetim > Dosya depolama alanları",
    "Go to your OpenProject {htmlLink} as an Administrator and start the setup and copy the values here." : "Yönetici olarak OpenProject {htmlLink} bağlantısına gideerek kurulumu başlatın ve değerleri buraya kopyalayın.",
    "Copy the following values back into the OpenProject {htmlLink} as an Administrator." : "Şu değerleri yönetici olarak OpenProject {htmlLink} bağlantısına geri kopyalayın.",
    "If you proceed you will need to update these settings with the new OpenProject OAuth credentials. Also, all users will need to reauthorize access to their OpenProject account." : "Devam ederseniz, bu ayarları yeni OpenProject OAuth kimlik bilgileriyle güncellemeniz gerekir. Ayrıca, tüm kullanıcıların yeniden OpenProject hesaplarına erişim izni vermeleri gerekir.",
    "Yes, replace" : "Evet, değiştir",
    "Are you sure that you want to reset this app and delete all settings and all connections of all Nextcloud users to OpenProject?" : "Bu uygulamayı sıfırlamak ve tüm ayarları ile tüm Nextcloud kullanıcıların OpenProject bağlantılarını silmek istediğinize emin misiniz?",
    "Reset OpenProject integration" : "OpenProject bütünleştirmesini sıfırla",
    "Yes, reset" : "Evet, sıfırla",
    "Please introduce a valid OpenProject host name" : "Lütfen geçerli bir OpenProject sunucu adı yazın",
    "URL is invalid" : "Adres geçersiz",
    "The URL should have the form \"https://openproject.org\"" : "Adres \"https://openproject.org\" biçiminde olmalıdır",
    "There is no valid OpenProject instance listening at that URL, please check the Nextcloud logs" : "Bu adresi dinleyen geçerli bir OpenProject kopyası yok. Lütfen Nextcloud günlüklerini denetleyin",
    "Response:" : "Yanıt:",
    "Server replied with an error message, please check the Nextcloud logs" : "Sunucu bir hata iletisi ile yanıt verdi. Lütfen Nextcloud günlüklerini denetleyin",
    "Documentation" : "Belgeler",
    "Accessing OpenProject servers with local addresses is not allowed." : "Yerel adreslerle OpenProject sunucularına erişilmesine izin verilmiyor.",
    "To be able to use an OpenProject server with a local address, " : "Yerel bir adresi olan bir OpenProject sunucusunu kullanabilmek için,",
    "The given URL redirects to '{location}'. Please do not use a URL that leads to a redirect." : "Belirtilen adres '{location}' adresine yönlendiriyor. Lütfen yönlendirmeye yol açan bir adres kullanmayın.",
    "Could not connect to the given URL, please check the Nextcloud logs" : "Verilen adres ile bağlantı kurulamadı. Lütfen Nextcloud günlüklerini denetleyin",
    "OpenProject admin options saved" : "OpenProject yönetici ayarları kaydedildi",
    "Failed to save OpenProject admin options" : "OpenProject yönetici ayarları kaydedilemedi",
    "If you proceed you will need to update the settings in your OpenProject with the new Nextcloud OAuth credentials. Also, all users in OpenProject will need to reauthorize access to their Nextcloud account." : "Devam ederseniz OpenProject kurulumunuzdaki ayarları yeni Nextcloud OAuth kimlik bilgileriyle güncellemeniz gerekir. Ayrıca, OpenProject üzerindeki tüm kullanıcıların yeniden Nextcloud hesaplarına erişim izni vermeleri gerekir.",
    "Failed to create Nextcloud OAuth client" : "Nextcloud OAuth istemcisi oluşturulamadı",
    "Default user configuration saved" : "Varsayılan kullanıcı yapılandırması kaydedildi",
    "Failed to save default user configuration" : "Varsayılan kullanıcı yapılandırması kaydedilemedi",
    "Connect to OpenProject" : "OpenProject bağlantısı kur",
    "Some OpenProject integration application settings are not working." : "OpenProject bütünleşme uygulamasının bazı ayarları çalışmıyor.",
    "Failed to redirect to OpenProject" : "OpenProject sitesine yönlendirilemedi",
    "Connected as {user}" : "{user} olarak bağlantı kuruldu",
    "Disconnect from OpenProject" : "OpenProject bağlantısını kes",
    "Warning, everything you type in the search bar will be sent to your OpenProject instance." : "Uyarı, arama çubuğuna yazdığınız her şey OpenProject kopyanıza gönderilecek.",
    "OpenProject options saved" : "OpenProject ayarları kaydedildi",
    "Incorrect access token" : "Erişim kodu geçersiz",
    "Invalid token" : "Kod geçersiz.",
    "OpenProject instance not found" : "OpenProject kopyası bulunamadı",
    "Failed to save OpenProject options" : "OpenProject ayarları kaydedilemedi",
    "Copied!" : "Kopyalandı!",
    "Copy value" : "Değeri kopyala",
    "Copied to the clipboard" : "Panoya kopyalandı",
    "Details" : "Ayrıntılar",
    "OpenProject integration" : "OpenProject bütünleştirmesi",
    "No connection with OpenProject" : "OpenProject bağlantısı yok",
    "Error connecting to OpenProject" : "OpenProject bağlantısı kurulurken sorun çıktı",
    "Could not fetch work packages from OpenProject" : "OpenProject üzerinden çalışma paketleri alınamadı",
    "No OpenProject links yet" : "Henüz bir OpenProject bağlantısı yok",
    "Unexpected Error" : "Beklenmeyen bir sorun çıktı",
    "To add a link, use the search bar above to find the desired work package" : "İstediğiniz iş paketini bulmak ve bir bağlantı olarak eklemek için yukarıdaki arama çubuğunu kullanın",
    "Start typing to search" : "Aramak için yazmaya başlayın",
    "Search for a work package to create a relation" : "İlişki kurulacak bir çalışma paketi arayın",
    "No OpenProject account connected" : "Bağlı bir OpenProject hesabı yok",
    "Work package linked successfully!" : "Çalışma paketinin bağlantısı kuruldu!",
    "Failed to link file to work package" : "Çalışma paketinin bağlantısı kurulamadı",
    "Mark as read" : "Okunmuş olarak işaretle",
    "No OpenProject notifications!" : "Herhangi bir OpenProject bildirimi yok!",
    "Failed to get OpenProject notifications" : "OpenProject bildirimleri alınamadı",
    "Notifications associated with Work package marked as read" : "Okunmuş olarak işaretlenmiş İş paketi ile ilişkili bildirimler",
    "Failed to mark notifications as read" : "Bildirimler okunmuş olarak işaretlenemedi",
    "Existing relations:" : "Var olan ilişkiler:",
    "Unlink Work Package" : "Çalışma paketinin bağlantısını kes",
    "Are you sure you want to unlink the work package?" : "Çalışma paketinin bağlantısını kesmek istediğinize emin misiniz?",
    "Confirm unlink" : "Bağlantı kesmeyi onayla",
    "Unlink" : "Bağlantıyı kes",
    "Work package unlinked" : "Çalışma paketinin bağlantısı kesildi",
    "Failed to unlink work package" : "Çalışma paketinin bağlantısı kesilemedi",
    "Successfully connected to OpenProject!" : "OpenProject ile bağlantı kuruldu!",
    "OAuth access token could not be obtained:" : "OAuth erişim kodu eklenemedi:",
    "OpenProject notifications" : "OpenProject bildirimleri",
    "OpenProject activity" : "OpenProject işlemleri",
    "_You have %s new notification in {instance}_::_You have %s new notifications in {instance}_" : ["{instance} için %s yeni bildiriminiz var","{instance} için %s yeni bildiriminiz var"],
    "Connected accounts" : "Bağlı hesaplar",
    "OpenProject URL is invalid, provide an URL in the form \"https://openproject.org\"" : "OpenProject adresi geçersiz. \"https://openproject.org\" biçiminde bir adres yazın",
    "No OpenProject detected at the URL" : "Adreste bir OpenProject bulunamadı",
    "Enable notifications for activity in my work packages" : "Çalışma paketlerim için etkinlik bildirimlerini etkinleştir"
},
"nplurals=2; plural=(n > 1);");
