OC.L10N.register(
    "integration_openproject",
    {
    "OpenProject" : "OpenProject",
    "Error getting OAuth access token" : "Chyba pri získavaní prístupového tokenu OAuth",
    "Error getting OAuth refresh token" : "Chyba pri získavaní obnovovacieho tokenu OAuth",
    "Error during OAuth exchanges" : "Chyba počas výmeny OAuth",
    "Direct download error" : "Chyba pri priamom sťahovaní",
    "This direct download link is invalid or has expired" : "Tento priamy odkaz na stiahnutie je neplatný alebo jeho platnosť vypršala",
    "folder not found or not enough permissions" : "adresár nebol nájdený alebo nie sú dostatočné oprávnenia",
    "OpenProject work packages" : "Pracovné balíčky OpenProject",
    "Bad HTTP method" : "Zlá metóda HTTP",
    "OAuth access token refused" : "Prístupový token OAuth bol zamietnutý",
    "OpenProject Integration" : "OpenProject integrácia",
    "Link Nextcloud files to OpenProject work packages" : "Prepojte súbory Nextcloud s pracovnými balíkmi OpenProject",
    "This application enables seamless integration with open source project management and collaboration software OpenProject.\n\nOn the Nextcloud end, it allows users to:\n\n* Link files and folders with work packages in OpenProject\n* Find all work packages linked to a file or a folder\n* Create work packages directly in Nextcloud\n* View OpenProject notifications via the dashboard\n* Search for work packages using Nextcloud's search bar\n* Link work packages in rich text fields via Smart Picker\n* Preview links to work packages in text fields\n* Link multiple files and folder to a work package at once\n\nOn the OpenProject end, users are able to:\n\n* Link work packages with files and folders in Nextcloud\n* Upload and download files directly to Nextcloud from within a work package\n* Open linked files in Nextcloud to edit them\n* Let OpenProject create shared folders per project\n\nFor more information on how to set up and use the OpenProject application, please refer to [integration setup guide](https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/) for administrators and [the user guide](https://www.openproject.org/docs/user-guide/nextcloud-integration/)." : "Táto aplikácia umožňuje bezproblémovú integráciu s open source softvérom na riadenie projektov a spoluprácu OpenProject.\n\nNextcloud ďalej umožňuje užívateľom:\n\n* Prepojenie súborov a adresárov s pracovnými balíkmi v OpenProject\n* Nájisť všetky pracovné balíky prepojené so súborom alebo adresárom\n* Vytvárať pracovné balíčky priamo v Nextcloud\n* Prezerať si upozornenia OpenProject cez dashboard\n* Vyhľadaýť pracovné balíky pomocou vyhľadávacieho panela Nextcloud\n* Prepojiť pracovné balíky v poliach s formátovaným textom pomocou funkcie Smart Picker\n* Ukážku odkazov na pracovné balíky v textových poliach\n* Prepojiť viacero súborov a adresárov s pracovným balíkom naraz\n\nĎalej môžu užívatelia OpenProject:\n\n* Prepojiť pracovné balíky so súbormi a adresármi v Nextcloud\n* Nahrávať a sťahovať súbory priamo do Nextcloud z pracovného balíka\n* Otvoriť prepojené súbory v Nextcloud a upraviť ich\n* OpenProject môže vytvárať zdieľané adresáre projektu\n\nĎalšie informácie o tom, ako nastaviť a používať aplikáciu OpenProject, nájdete v [sprievodcovi nastavením integrácie](https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/) pre administrátorov a v [užívateľskej príručke](https://www.openproject.org/docs/user-guide/nextcloud-integration/).",
    "Keep current setup" : "Ponechať súčasné nastavenie",
    "Complete without project folders" : "Dokončiť bez projektových adresárov",
    "Setup OpenProject user, group and folder" : "Nastaviť uživateľa, skupinu a adresár OpenProjectu",
    "Administration > File storages" : "Administrácia > Úložiská súborov",
    "Copy the following values back into the OpenProject {htmlLink} as an Administrator." : "Skopírujte nasledujúce hodnoty späť do OpenProject {htmlLink} ako administrátor.",
    "Setting up the OpenProject user, group and group folder was not possible. Please check this {htmlLink} on how to resolve this situation." : "Nastavenie užívateľa, skupiny a adresára skupiny OpenProject nebolo možné. Prečítajte si prosím, ako túto situáciu vyriešiť {htmlLink}.",
    "Admin Audit" : "Audit administrátora",
    "documentation" : "dokumentácia",
    "To activate audit logs for the OpenProject integration, please enable the {htmlLinkForAdminAudit} app and follow the configuration steps outlined in the {htmlLinkForDocumentaion}." : "Ak chcete aktivovať protokoly auditu pre integráciu OpenProject, povoľte aplikáciu {htmlLinkForAdminAudit} a postupujte podľa konfiguračných krokov uvedených v {htmlLinkForDocumentaion}.",
    "Please read our guide on {htmlLink}." : "Prosím prečítajte si náš návod tu {htmlLink}.",
    "Please introduce a valid OpenProject hostname" : "Prosím zadajte platný názov hostiteľa OpenProject",
    "URL is invalid" : "URL je neplatné",
    "The URL should have the form \"https://openproject.org\"" : "URL musí byť vo formáte \"https://openproject.org\"",
    "There is no valid OpenProject instance listening at that URL, please check the Nextcloud logs" : "Na tejto adrese URL nepočúva žiadna platná inštancia OpenProject, skontrolujte logy Nextcloud",
    "Response:" : "Odpoveď:",
    "Server replied with an error message, please check the Nextcloud logs" : "Server vrátil chybovú hlášku, prosím skontrolujte logy Nextcloudu",
    "Documentation" : "Dokumentácia",
    "OpenProject admin options saved" : "Nastavenia administrátora OpenProjectu boli uložené",
    "Failed to save OpenProject admin options" : "Nepodarilo sa uložiť nastavenia administrátora OpenProjectu",
    "Failed to perform revoke request due to connection error with the OpenProject server" : "Nepodarilo sa vykonať požiadavku na zrušenie z dôvodu chyby spojenia so serverom OpenProject",
    "Failed to revoke some users' OpenProject OAuth access tokens" : "Nepodarilo sa odvolať prístupové tokeny OAuth OpenProject pre niektorých používateľov",
    "Successfully revoked users' OpenProject OAuth access tokens" : "OAuth Prístupové tokeny užívateľov OpenProject boli úspešne odvolané",
    "If you proceed you will need to update the settings in your OpenProject with the new Nextcloud OAuth credentials. Also, all users in OpenProject will need to reauthorize access to their Nextcloud account." : "Ak budete pokračovať, budete musieť aktualizovať nastavenia vo svojom OpenProject pomocou nových poverení Nextcloud OAuth. Všetci užívatelia v OpenProject budú musieť znova autorizovať prístup k svojmu účtu Nextcloud.",
    "Replace Nextcloud OAuth values" : "Nahradiť hodnoty Nextcloud OAuth",
    "Yes, replace" : "Áno, nahradiť",
    "Cancel" : "Zrušiť",
    "If you proceed, your old application password for the OpenProject user will be deleted and you will receive a new OpenProject user password." : "Ak budete pokračovať, vaše staré heslo aplikácie pre užívateľa OpenProject bude vymazané a dostanete nové heslo užívateľa OpenProject.",
    "Replace user app password" : "Nahradiť uživateľské heslo aplikácie",
    "Failed to create Nextcloud OAuth client" : "Nepodarilo sa vytvoriť Nextcloud OAuth klienta",
    "Default user configuration saved" : "Predvolená konfigurácia užívateľa bola uložená",
    "Failed to save default user configuration" : "Nepodarilo sa uložiť predvolenú konfiguráciu užívateľa",
    "OpenProject server" : "OpenProject server",
    "OpenProject host" : "OpenProject server",
    "Please introduce your OpenProject hostname" : "Uveďte svoj názov hostiteľa OpenProject",
    "Edit server information" : "Upraviť informácie o serveri",
    "Save" : "Uložiť",
    "Authentication method" : "Autentifikačná metóda",
    "Need help setting this up?" : "Potrebujete pomôcť s týmto nastavením?",
    "Edit authentication method" : "Upraviť metódu autentifikácie",
    "Authentication settings" : "Nastavenia autentifikácie",
    "OIDC Provider Type" : "Typ poskytovateľa OIDC",
    "OIDC Provider" : "Poskytovateľ OIDC",
    "Select a provider *" : "Vybrať poskytovateľa *",
    "Select an OIDC provider" : "Vybrať poskytovateľa OIDC",
    "OIDC provider" : "Poskytovateľ OIDC",
    "Edit authentication settings" : "Upraviť nastavenia autentifikácie",
    "OpenProject OAuth settings" : "Nastavenia OpenProject OAuth",
    "Replace OpenProject OAuth values" : "Nahradiť hodnoty OpenProject OAuth",
    "Nextcloud OAuth client" : "Klient Nextcloud Oauth",
    "Yes, I have copied these values" : "Áno, tieto hodnoty som skopíroval",
    "Create Nextcloud OAuth values" : "Vytvoriť hodnoty pre Nextcloud OAuth",
    "Project folders (recommended)" : "Adresáre projetku (odporúčané)",
    "Automatically managed folders" : "Automaticky spravované adresáre",
    "We recommend using this functionality but it is not mandatory. Please activate it in case you want to use the automatic creation and management of project folders." : "Odporúčame používať túto funkciu, ale nie je povinná. Aktivujte si ju v prípade, že chcete využívať automatické vytváranie a správu adresárov projektu.",
    "Let OpenProject create folders per project automatically. It will ensure that every team member has always the correct access permissions." : "OpenProject môže vytvárať adresáre pre projekt automaticky. Zabezpečí to, že každý člen tímu má vždy správne prístupové oprávnenia.",
    "OpenProject user, group and folder" : "Užívatelia, skupiny a adresáre OpenProject",
    "For automatically managing project folders, this app needs to setup a special group folder, assigned to a group and managed by a user, each called \"OpenProject\"." : "Pre automatickú správu adresárov projektu musí táto aplikácia nastaviť špeciálny adresár skupiny, priradený skupine a spravovaný užívateľom, každý s názvom „OpenProject“.",
    "The app will never delete files or folders, even if you deactivate this later." : "Aplikácia nikdy neodstráni súbory ani adresáre, aj keď to neskôr deaktivujete.",
    "Retry setup OpenProject user, group and folder" : "Zopakujte nastavenie Užívateľa, skupiny a adresára  OpenProject",
    "Automatically managed folders:" : "Automaticky spravované adresáre:",
    "Administration Settings > OpenProject" : "Nastavenia administrácie > OpenProject",
    "Failed to redirect to OpenProject" : "Nepodarilo sa presmerovať na OpenProject",
    "Connect to OpenProject" : "Pripojiť k OpenProject",
    "OpenProject options saved" : "Nastavenia OpenProjectu boli uložené",
    "Incorrect access token" : "Nesprávny prístupový token",
    "Invalid token" : "Neplatný token",
    "OpenProject instance not found" : "Inštancia OpenProject nebola nájdená",
    "Failed to save OpenProject options" : "Nepodarilo sa uložiť nastavenia OpenProjectu",
    "Connected as {user}" : "Pripojený ako {user}",
    "Disconnect from OpenProject" : "Odpojiť od OpenProject",
    "Enable navigation link" : "Povoliť navigačný odkaz",
    "Enable unified search for tickets" : "Zapnúť jednotné vyhľadávanie tiketov",
    "Warning, everything you type in the search bar will be sent to your OpenProject instance." : "Varovanie, všetko čo napíšete do vyhľadávania bude odoslané do OpenProject inštancie.",
    "All terms of services are signed for user \"OpenProject\" successfully!" : "Všetky podmienky používania služieb boli pre užívateľa \"OpenProject\" úspešne podpísané!",
    "Failed to sign terms of services for user \"OpenProject\"" : "Nepodarilo sa podpísať zmluvné podmienky pre užívateľa „OpenProject“",
    "For user \"OpenProject\", several \"Terms of services\" have not been signed." : "Pre používateľa „OpenProject“ nebolo podpísaných niekoľko „Podmienok poskytovania služieb“.",
    "Sign any unsigned \"Terms Of Services\" for user \"OpenProject\"." : "Podpíšte všetky nepodpísané \"Podmienky poskytovania služieb\" pre používateľa \"OpenProject\".",
    "Sign Terms of services" : "Podpísať Podmienky používania služieb",
    "Copied!" : "Skopírované!",
    "Copy value" : "Kopírovať hodnotu",
    "Copied to the clipboard" : "Skopírované do schránky",
    "Details" : "Podrobnosti",
    "setting up a Nextcloud file storage" : "nastaviť úložisko súborov Nextcloud",
    "Learn how to get the most out of the OpenProject integration by visiting our {htmlLink}." : "Zistite, ako vyťažiť maximum z integrácie OpenProject návštevou {htmlLink}.",
    "No connection with OpenProject" : "Nie je dostupné pripojenie k OpenProject",
    "Error connecting to OpenProject" : "Chyba pri pripájaní k OpenProject",
    "Could not fetch work packages from OpenProject" : "Nepodarilo sa načítať pracovné balíky z OpenProject",
    "No OpenProject notifications" : "Žiadne upozornenia od OpenProject",
    "Add a new link to all selected files" : "Pridať nový odkaz pre všetky označené súbory",
    "No OpenProject links yet" : "Žiadne odkazy OpenProject",
    "Unexpected Error" : "Neočakávaná chyba",
    "To add a link, use the search bar above to find the desired work package" : "Ak chcete pridať odkaz, pomocou vyhľadávacieho panela vyššie nájdite požadovaný pracovný balík",
    "No OpenProject account connected" : "Nie je pripojený žiadny OpenProject účet",
    "Start typing to search" : "Začnite písať pre vyhľadanie",
    "No matching work packages found" : "Nenašli sa žiadne zodpovedajúce pracovné balíky",
    "Search for work packages" : "Vyhľadať pracovné balíky",
    "Search for a work package to create a relation" : "Ak chcete vytvoriť súvislosť, vyhľadajte pracovný balík",
    "Work package creation was not successful." : "Vytváranie pracovného balíka nebolo úspešné.",
    "Work package created successfully." : "Pracovné balíky boli úspešne vytvorené.",
    "Link to work package created successfully!" : "Odkaz na pracovný balík bol úspešne vytvorený!",
    "Links to work package created successfully for selected files!" : "Odkazy na pracovný balík boli úspešne vytvorené pre vybrané súbory!",
    "Create and link a new work package" : "Vytvorte a prepojte nový pracovný balík",
    "No matching work projects found!" : "Nenašli sa žiadne zodpovedajúce pracovné balíky!",
    "Status is not set to one of the allowed values." : "Stav nie je nastavený na jednu z povolených hodnôt.",
    "Project *" : "Projekt *",
    "Select a project" : "Vybrať projekt",
    "Subject *" : "Predmet *",
    "Work package subject" : "Predmet pracovného balíka",
    "Type *" : "Typ *",
    "Select project type" : "Vybrať typ projektu",
    "Please select a project" : "Prosím vyberte projekt",
    "Status *" : "Stav *",
    "Select project status" : "Vybrať stav projektu",
    "Assignee" : "Vlastník",
    "Select a user or group" : "Vyberte používateľa alebo skupinu",
    "Description" : "Popis",
    "Work package description" : "Popis pracovného balíku",
    "Create" : "Vytvoriť",
    "Mark as read" : "Označiť ako prečítané",
    "Failed to get OpenProject notifications" : "Chyba pri získavaní upozornení z OpenProject",
    "Date alert" : "Dátum upozornenia",
    "assignee" : "vlastník",
    "accountable" : "zodpovedný",
    "watcher" : "pozorovateľ",
    "commented" : "comentované",
    "mentioned" : "spomenuté",
    "Notifications associated with Work package marked as read" : "Upozornenie priradené k Pracovnému balíku bolo označené ako prečítané",
    "Failed to mark notifications as read" : "Nepodarilo sa označiť upozornenie ako prečítané",
    "Failed to link selected files to work package" : "Nepodarilo sa prepojiť vybrané súbory s pracovným balíkom",
    "Retry linking remaining files" : "Opakovať vytvorenie odkazu pre ostávajúce súbory",
    "Files selected: ${getTotalNoOfFilesSelectedInChunking}" : "Vybrané súbory: ${getTotalNoOfFilesSelectedInChunking}\n ",
    "Files successfully linked: ${getTotalNoOfFilesAlreadyLinkedInChunking}" : "Súbory boli úspešne pripojené: ${getTotalNoOfFilesAlreadyLinkedInChunking}",
    "Files failed to be linked: ${getTotalNoOfFilesNotLinkedInChunking}" : "Súbory ktoré sa nepodarilo pripojiť: ${getTotalNoOfFilesNotLinkedInChunking}",
    "${getTotalNoOfFilesAlreadyLinkedInChunking} of ${getTotalNoOfFilesSelectedInChunking} files linked" : "$ {getTotalNoOfFilesAlreadyLinkedInChunking} z ${getTotalNoOfFilesSelectedInChunking} pripojených súborov",
    "${getProgressValueOfMultipleFilesLinked}%" : "${getProgressValueOfMultipleFilesLinked}%",
    "Are you sure you want to unlink the work package?" : "Ste si istí, že chcete odpojiť pracovný balík?",
    "Confirm unlink" : "Potvrdiť odpojenie",
    "Unlink" : "Odpojiť",
    "Work package unlinked" : "Pracovný balík bol odpojený",
    "Failed to unlink work package" : "Nepodarilo sa odpojiť pracovný balík",
    "Existing relations:" : "Existujúce vzťahy:",
    "Unlink Work Package" : "Odpojiť pracovný balík",
    "OpenProject work package picker" : "Výber pracovného balíka OpenProject",
    "OpenProject API error" : "Chyba OpenProject API",
    "OpenProject settings" : "Nastavenia OpenProject",
    "This app is required to use the OIDC authentication method" : "Táto aplikácia je potrebná na používanie metódy overenia OIDC",
    "Download and enable it" : "Stiahnuť a povoliť",
    "This feature is not available for this user account" : "Táto funkcia nie je dostupná pre tento uživateľský účet",
    "Unauthorized to connect to OpenProject" : "Nemáte práva pre pripojenie k OpenProject",
    "OpenProject client ID" : "ID klienta OpecProject",
    "Token Exchange" : "Výmena tokenu",
    "Enable token exchange" : "Povoliť výmenu tokenu",
    "You can get this value from your identity provider when you configure the client" : "Túto hodnotu môžete získať od svojho poskytovateľa identity, keď konfigurujete klienta",
    "Nextcloud Hub" : "Nextcloud Hub",
    "External Provider" : "Externý poskytovateľ",
    "When enabled, the app will try to obtain a token for the given audience from the identity provider. If disabled, it will use the access token obtained during the login process." : "Po povolení sa aplikácia pokúsi získať token od poskytovateľa identity. Ak je zakázané, použije sa prístupový token získaný počas procesu prihlásenia.",
    "The \"{app}\" app is not installed" : "Aplikácia \"{app}\" nie je nainštalovaná",
    "The \"{app}\" app is not supported" : "Aplikácia \"{app}\" nie je podporovaná",
    "The \"{app}\" app is not enabled or supported" : "Aplikácia \"{app}\" nie je podporovaná alebo povolená",
    "Requires app version \"{minimumAppVersion}\" or later" : "Vyžaduje sa verzia aplikácie \"{minimumAppVersion}\" alebo vyššia",
    "You can configure OIDC providers in the {settingsLink}" : "Môžete nastaviť poskytovateľov OIDC v {settingsLink}",
    "Link to work package" : "Odkaz na pracovný balík",
    "Failed to link file to work package" : "Nepodarilo sa pripojiť pracovný balík",
    "Successfully connected to OpenProject!" : "Úspešne pripojené k OpenProject!",
    "OAuth access token could not be obtained:" : "Prístupový token OAuth sa nepodarilo získať:",
    "Displays a link to your OpenProject instance in the Nextcloud header." : "Zobraziť odkaz na vašu inštanciu OpenProject v hlavičke Nextcloud.",
    "Allows you to search OpenProject work packages via the universal search bar in Nextcloud." : "Umožňuje vám vyhľadávať pracovné balíky OpenProject prostredníctvom univerzálneho vyhľadávacieho panela v Nextcloud.",
    "Two-way OAuth 2.0 authorization code flow" : "Tok 2-faktorového autorizačného kódu OAuth 2.0",
    "Single-Sign-On through OpenID Connect Identity Provider" : "Jednotné prihlásenie cez OpenID Connect Identity Provider",
    "OpenProject notifications" : "Notifikácie OpenProject",
    "OpenProject activity" : "OpenProject aktivity",
    "_You have %s new notification in {instance}_::_You have %s new notifications in {instance}_" : ["Máte %s oznámenie v {instance}","Máte %s oznámenia v {instance}","Máte %s oznámení v {instance}","Máte %s oznámenia v {instance}"],
    "Connected accounts" : "Prepojené účty",
    "This application enables seamless integration with open source project management and collaboration software OpenProject.\n\nOn the Nextcloud end, it allows users to:\n\n* Link files and folders with work packages in OpenProject\n* Find all work packages linked to a file or a folder\n* View OpenProject notifications via the dashboard\n* Search for work packages using Nextcloud's search bar\n\nOn the OpenProject end, users are able to:\n\n* View all Nextcloud files and folders linked to a work package\n* Download linked files or open them in Nextcloud to edit them\n\nFor more information on how to set up and use the OpenProject application, please refer to [integration setup guide](https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/) for administrators and [the user guide](https://www.openproject.org/docs/user-guide/nextcloud-integration/)." : "Táto aplikácia umožňuje bezproblémovú integráciu s open source softvérom pre riadenie projektov a spoluprácu OpenProject.\n\nNextcloud umožňuje užívateľom:\n\n* Prepojiť súbory a adresáre s pracovnými balíkmi v OpenProject\n* Nájisť všetky pracovné balíky prepojené so súborom alebo adresárom\n* Zobraziť upozornenia OpenProject cez dashboard\n* Vyhľadať pracovné balíky pomocou vyhľadávacieho panela Nextcloud\n\nĎalej môžu užívatelia OpenProject:\n\n* Zobraziť všetky súbory a adresáre Nextcloud prepojené s pracovným balíkom\n* Stiahnuť si prepojené súbory alebo ich otvoriť v Nextcloud a upraviť ich\n\nĎalšie informácie o tom, ako nastaviť a používať aplikáciu OpenProject, nájdete v [sprievodcovi nastavením integrácie](https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/) pre administrátorov a v [používateľskej príručke](https://www.openproject.org/docs/user-guide/nextcloud-integration/).",
    "Please introduce a valid OpenProject host name" : "Zadajte platný názov hostiteľa OpenProject",
    "If you proceed you will need to update these settings with the new OpenProject OAuth credentials. Also, all users will need to reauthorize access to their OpenProject account." : "Ak budete pokračovať, budete musieť aktualizovať tieto nastavenia pomocou nových poverení OpenProject OAuth. Všetci užívatelia budú musieť znova autorizovať prístup k svojmu účtu OpenProject.",
    "Are you sure that you want to reset this app and delete all settings and all connections of all Nextcloud users to OpenProject?" : "Naozaj chcete obnoviť túto aplikáciu a odstrániť všetky nastavenia a všetky pripojenia všetkých užívateľov Nextcloud k OpenProject?",
    "Reset OpenProject integration" : "Resetovať integráciu OpenProject",
    "Yes, reset" : "Áno, resetovať",
    "OpenProject URL is invalid, provide an URL in the form \"https://openproject.org\"" : "URL OpenProject je neplatná, zadajte URL vo formáte \"https://openproject.org\"",
    "No OpenProject detected at the URL" : "Na danej URL nebol detekovaný OpenProject",
    "Please introduce your OpenProject host name" : "Zadajte názov serveru kde prevádzkujete vašu inštanciu OpenProject-u",
    "Reset" : "Resetovať",
    "Some OpenProject integration application settings are not working. Please contact your Nextcloud administrator." : "Niektoré nastavenia integrácie aplikácie OpenProject nefungujú. Kontaktujte svojho správcu Nextcloud.",
    "Enable notifications for activity in my work packages" : "Povoliť upozornenia na aktivitu v mojich pracovných balíkoch",
    "OpenProject integration" : "OpenProject integrácia",
    "Work package linked successfully!" : "Pracovný balík bol úspešne pripojený!",
    "No OpenProject notifications!" : "Žiadne upozornenia od OpenProject!",
    "Invalid key" : "Neplatný kľúč",
    "Default user settings" : "Predvolené nastavenia užívateľa",
    "A new user will receive these defaults and they will be applied to the integration app till the user changes them." : "Nový užívateľ dostane tieto predvolené hodnoty a budú sa používať v integračnej aplikácii, kým ich užívateľ nezmení.",
    "Failed to revoke some user(s) OpenProject OAuth access token(s)" : "Nepodarilo sa odvolať OAuth prístupové tokeny niektorých užívateľov OpenProject",
    "Successfully revoked user(s) OpenProject OAuth access token(s)" : "OAuth Prístupový(é) token(y) užívateľov OpenProject boli úspešne odvolané",
    "This application enables seamless integration with open source project management and collaboration software OpenProject.\n\nOn the Nextcloud end, it allows users to:\n\n* Link files and folders with work packages in OpenProject\n* Find all work packages linked to a file or a folder\n* View OpenProject notifications via the dashboard\n* Search for work packages using Nextcloud's search bar\n* Link work packages in rich text fields via Smart Picker\n* Preview links to work packages in text fields\n\nOn the OpenProject end, users are able to:\n\n* Link work packages with files and folders in Nextcloud\n* Upload and download files directly to Nextcloud from within a work package\n* Open linked files in Nextcloud to edit them\n* Let OpenProject create shared folders per project\n\nFor more information on how to set up and use the OpenProject application, please refer to [integration setup guide](https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/) for administrators and [the user guide](https://www.openproject.org/docs/user-guide/nextcloud-integration/)." : "Táto aplikácia umožňuje bezproblémovú integráciu s open source softvérom na riadenie projektov a spoluprácu OpenProject.\n\nĎalej Nextcloud umožňuje užívateľom:\n\n* Prepojiť súbory a adresáre s pracovnými balíkmi v OpenProject\n* Nájisť všetky pracovné balíky prepojené so súborom alebo adresárom\n* Zobraziť upozornení OpenProject cez dashboard\n* Vyhľadať pracovné balíky pomocou vyhľadávacieho panela Nextcloud\n* Prepojiť pracovné balíky v poliach s formátovaným textom pomocou funkcie Smart Picker\n* Ukázať odkazov na pracovné balíky v textových poliach\n\nĎalej v OpenProject môžu užívatelia:\n\n* Prepojiť pracovné balíky so súbormi a adresármi v Nextcloud\n* Nahrávať a sťahovať súbory priamo do Nextcloud z pracovného balíka\n* Otvoriť prepojené súbory v Nextcloud a upraviť ich\n* OpenProject môže vytvárať zdieľané aresáre pre projekt\n\nĎalšie informácie o tom, ako nastaviť a používať aplikáciu OpenProject, nájdete v [sprievodcovi nastavením integrácie](https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/) pre správcov a v [užívateľskej príručke](https://www.openproject.org/docs/user-guide/nextcloud-integration/).",
    "Please install the \"Group folders\" app to be able to use automatic managed folders or deactivate the automatically managed folders." : "Nainštalujte si aplikáciu „Skupinové adresáre“, aby ste mohli používať automaticky spravované adresáre alebo deaktivovať automaticky spravované adresáre.",
    "Reset OpenProject Integration" : "Resetovať integráciu OpenProject",
    "Please link a project to this Nextcloud storage" : "Prepojte projekt s týmto úložiskom Nextcloud",
    "Download and enable it here" : "Stiahnite si a aktivujte to tu"
},
"nplurals=4; plural=(n % 1 == 0 && n == 1 ? 0 : n % 1 == 0 && n >= 2 && n <= 4 ? 1 : n % 1 != 0 ? 2: 3);");
