-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: aziz_portfolio
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','editor') DEFAULT 'admin',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (3,'admin','admin123','admin@azizportfolio.com','Aziz Rahman Noyan','admin','2026-03-22 12:47:05'),(4,'Aziz','Aziz1234','matiasmasih@gmail.com','Aziz Rahman noyan','admin','2026-03-22 12:51:46');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_posts`
--

DROP TABLE IF EXISTS `blog_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `content` text NOT NULL,
  `excerpt` varchar(500) DEFAULT NULL,
  `featured_image` varchar(500) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `views` int DEFAULT '0',
  `status` enum('draft','published') DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_posts`
--

LOCK TABLES `blog_posts` WRITE;
/*!40000 ALTER TABLE `blog_posts` DISABLE KEYS */;
INSERT INTO `blog_posts` VALUES (1,'Tervetuloa blogiini – Matkani kohti IT-alaa','tervetuloa-blogiini-matkani-kohti-it-alaa','<h2>Tervetuloa blogiini!</h2>\n<p>Hei ja tervetuloa ensimmäiseen blogipostaukseeni! Olen todella innoissani voidessani aloittaa tämän blogin ja jakaa ajatuksiani, kokemuksiani ja oppimiani asioita kanssasi. Tämä blogi on minulle tärkeä askel matkallani kohti IT-alaa, ja toivon, että löydät täältä inspiraatiota, hyödyllistä tietoa ja ehkä jopa ratkaisuja omiin haasteisiisi.</p>\n\n<h2>Kuka olen ja miksi tämä blogi?</h2>\n<p>Olen <strong>Aziz Rahman Noyan</strong>, 30-vuotias verkkokehittäjäksi opiskeleva Vantaalta. Taustani on kuitenkin hyvin erilainen kuin monella muulla alalla työskentelevällä. Ennen IT-uraa työskentelin rakennus- ja sähköalalla useita vuosia. Tämä tausta on antanut minulle vahvan perustan tekniseen ajatteluun, ongelmanratkaisuun ja tarkkuuteen – kaikki taitoja, joista on valtavasti hyötyä ohjelmointityössä.</p>\n\n<p>Päätös alanvaihdosta ei tullut yhdessä yössä. Olin aina ollut kiinnostunut tietokoneista ja teknologiasta, mutta en ollut aiemmin uskaltanut ryhtyä tosissani opiskelemaan. Lopulta päätin tarttua tilaisuuteen ja hakea <strong>Stadin ammatti- ja aikuisopistoon</strong>, jossa aloitin opinnot vuonna 2023. Se on ollut yksi elämäni parhaista päätöksistä.</p>\n\n<h2>Mitä tämä blogi tulee sisältämään?</h2>\n<p>Blogissa aion kirjoittaa monipuolisesti eri aiheista, jotka liittyvät oppimiseeni ja kehitykseeni IT-alalla. Tässä muutamia teemoja, joita tulet näkemään:</p>\n\n<ul>\n<li><strong>💻 Oppimispolkuni</strong> – mitä olen oppinut, mitkä asiat ovat olleet haastavia ja miten olen ratkaissut ongelmia</li>\n<li><strong>🚀 Projektit</strong> – esittelen omia projektejani, kuten tätä portfolio-sivustoa ja muita rakentamiani sovelluksia</li>\n<li><strong>📚 Web-kehitys</strong> – jaan tietoa HTML:stä, CSS:stä, JavaScriptistä, PHP:stä, MySQL:stä ja muista teknologioista</li>\n<li><strong>🔧 Työkalut ja resurssit</strong> – suosittelen hyödyllisiä työkaluja, kirjoja ja verkkokursseja</li>\n<li><strong>💡 Vinkit aloittelijoille</strong> – neuvoja niille, jotka ovat aloittamassa tai harkitsevat alanvaihtoa</li>\n<li><strong>🎯 Urapolkuni</strong> – kerron työnhausta, harjoitteluista ja tulevaisuuden suunnitelmistani</li>\n</ul>\n\n<h2>Miksi päätin vaihtaa alaa?</h2>\n<p>Rakennus- ja sähköala opetti minulle monia tärkeitä asioita: tarkkuutta, turvallisuutta, tiimityötä ja projektien hallintaa. Olen kiitollinen siitä kokemuksesta. Kuitenkin huomasin, että intohimoni oli aina ollut teknologiassa ja tietokoneissa. Halusin tehdä työtä, jossa saan käyttää luovuuttani ja teknistä osaamistani yhdessä.</p>\n\n<p>Kun aloitin ohjelmoinnin opiskelun, tajusin heti, että tämä on sitä, mitä haluan tehdä loppuelämäni. On uskomattoman palkitsevaa nähdä, kun idea muuttuu toimivaksi verkkosivuksi tai -sovellukseksi.</p>\n\n<h2>Mitä olen oppinut tähän mennessä?</h2>\n<p>Opintojeni aikana olen oppinut useita tärkeitä teknologioita:</p>\n<ul>\n<li><strong>HTML5 & CSS3</strong> – verkkosivujen rakenteen ja ulkoasun perusta</li>\n<li><strong>JavaScript</strong> – interaktiivisten elementtien luominen ja dynaamisuus</li>\n<li><strong>PHP & MySQL</strong> – backend-kehitys, tietokantojen hallinta ja dynaamiset verkkosivut</li>\n<li><strong>Git & GitHub</strong> – versionhallinta ja yhteistyö muiden kehittäjien kanssa</li>\n<li><strong>Responsiivinen suunnittelu</strong> – sivustot toimivat kaikilla laitteilla (mobiili, tabletti, tietokone)</li>\n<li><strong>jQuery & AJAX</strong> – dynaamisen sisällön lataaminen ilman sivun uudelleenlatausta</li>\n</ul>\n\n<p>Olen myös oppinut, että ohjelmointi on <strong>jatkuvaa oppimista</strong>. Uusia teknologioita ja työkaluja tulee koko ajan, ja se tekee alasta niin kiehtovan. Jokainen uusi projekti tuo mukanaan uusia haasteita ja mahdollisuuksia oppia.</p>\n\n<h2>Haasteet ja miten olen selvinnyt niistä</h2>\n<p>Matka ei ole ollut pelkästään helppo. Alussa tuntui, että tietoa on liikaa ja kaikki on vaikeaa. Mutta olen oppinut, että <strong>tärkeintä on jakaa isot ongelmat pienempiin osiin</strong> ja ratkaista ne yksi kerrallaan.</p>\n\n<p>Kun en ymmärrä jotain asiaa, etsin tietoa, katson videoita, kysyn apua opettajilta ja muilta opiskelijoilta. Yhteisö on tärkeä – kukaan ei opi kaikkea yksin.</p>\n\n<p>Toinen tärkeä oppi on ollut <strong>virheistä oppiminen</strong>. Koodatessa virheitä tulee koko ajan, ja se on ihan normaalia. Jokainen bugi on mahdollisuus oppia jotain uutta.</p>\n\n<h2>Vinkkejä aloittelijoille</h2>\n<p>Jos olet vasta aloittamassa ohjelmoinnin opiskelua tai harkitset alanvaihtoa, tässä muutama vinkki, jotka ovat auttaneet minua:</p>\n<ul>\n<li><strong>🌱 Aloita perusteista</strong> – opettele HTML, CSS ja JavaScript ennen monimutkaisempia teknologioita. Hyvät perusteet kantavat pitkälle.</li>\n<li><strong>💪 Tee omia projekteja</strong> – teoria on tärkeää, mutta käytäntö vie eteenpäin. Rakenna jotain, mikä kiinnostaa sinua.</li>\n<li><strong>❌ Älä pelkää virheitä</strong> – ne ovat parhaita opettajia. Jokainen virhe on askel eteenpäin.</li>\n<li><strong>📚 Käytä versionhallintaa (Git)</strong> – se on alan perustaito, ja se kannattaa opetella heti alussa.</li>\n<li><strong>🤝 Liity yhteisöihin</strong> – muilta oppii paljon. Kysy, keskustele ja jaa omaa osaamistasi.</li>\n<li><strong>📖 Pidä oppimispäiväkirjaa</strong> – kirjoita ylös, mitä olet oppinut. Se auttaa kertaamaan ja näkemään edistymisen.</li>\n<li><strong>⏰ Ole kärsivällinen</strong> – oppiminen vie aikaa. Älä vertaa itseäsi muihin, vaan keskity omaan kehitykseesi.</li>\n</ul>\n\n<h2>Mitä odottaa tulevaisuudessa?</h2>\n<p>Tässä blogissa tulet näkemään:</p>\n<ul>\n<li>📝 <strong>Viikoittaisia päivityksiä</strong> – mitä olen oppinut ja millaisia projekteja olen tehnyt</li>\n<li>🔧 <strong>Teknisiä oppaita</strong> – käytännön esimerkkejä ja ratkaisuja yleisiin ongelmiin</li>\n<li>🎓 <strong>Oppimisresursseja</strong> – suosituksia kursseista, kirjoista ja työkaluista</li>\n<li>💼 <strong>Työnhaku ja ura</strong> – kokemuksia työnhausta ja harjoitteluista IT-alalla</li>\n<li>🌍 <strong>Tapahtumat ja verkostoituminen</strong> – osallistumiseni alan tapahtumiin</li>\n</ul>\n\n<h2>Lopuksi</h2>\n<p>Olen innoissani tästä uudesta matkasta ja siitä, että pääsen jakamaan sitä kanssasi. Toivon, että blogistani on sinulle hyötyä, olitpa sitten aloittelija, kokenut kehittäjä tai joku, joka vain pohtii uraa IT-alalla.</p>\n\n<p>Jos sinulla on kysyttävää, ideoita blogin aiheiksi tai haluat vain vaihtaa ajatuksia, ota rohkeasti yhteyttä. Jätä kommentti tai laita viestiä – kuulen mielelläni sinusta!</p>\n\n<p>Kiitos, että luit ensimmäisen postaukseni. Seuraavassa postauksessa aion kertoa tarkemmin <strong>ensimmäisestä merkittävästä projektistani – Kirjastojärjestelmästä</strong>, jonka rakensin PHP:llä ja MySQL:llä.</p>\n\n<p><strong>Pysy kuulolla ja muista: jokainen suuri kehittäjä on ollut joskus aloittelija. Tärkeintä on aloittaa!</strong> 🚀</p>','Tervetuloa blogiini! Tässä ensimmäisessä postauksessa kerron itsestäni, taustastani rakennus- ja sähköalalta, miksi päätin ryhtyä verkkokehittäjäksi, mitä olen oppinut ja mitä tämä blogi tulee sisältämään.',NULL,'Web-kehitys',NULL,1,'published','2026-03-22 14:18:25','2026-03-22 12:18:25','2026-03-22 12:24:04'),(2,'Ensimmäinen blogipostaukseni – Matkani verkkokehittäjäksi','ensimmainen-blogipostaus-verkkokehittajaksi','<h2>Tervetuloa blogiini!</h2>\n<p>Hei ja tervetuloa ensimmäiseen blogipostaukseeni! Olen innoissani voidessani jakaa ajatuksiani ja kokemuksiani tässä blogissa. Tässä ensimmäisessä postauksessa kerron itsestäni, taustastani ja siitä, mikä minut sai päätymään verkkokehityksen pariin.</p>\n\n<h2>Kuka olen?</h2>\n<p>Olen <strong>Aziz Rahman Noyan</strong>, 30-vuotias verkkokehittäjä Vantaalta. Taustani on kuitenkin hyvin erilainen kuin monella muulla alalla työskentelevällä. Ennen IT-alaa työskentelin rakennus- ja sähköalalla useita vuosia. Tämä tausta on antanut minulle vahvan perustan tekniseen ajatteluun, ongelmanratkaisuun ja tarkkuuteen – kaikki taitoja, joista on valtavasti hyötyä ohjelmointityössä.</p>\n\n<h2>Miksi verkkokehittäjä?</h2>\n<p>Kiinnostukseni tietotekniikkaan on aina ollut vahva, mutta vasta muutama vuosi sitten päätin ryhtyä tosissani opiskelemaan ohjelmointia. Rakennus- ja sähköalalla opin, miten tärkeää on ymmärtää kokonaisuuksia ja nähdä asioiden taustalla olevat periaatteet. Samalla tavalla verkkokehityksessä on kyse kokonaisuuksien hallinnasta – miten etupää (frontend), tietokannat ja palvelin (backend) toimivat saumattomasti yhdessä.</p>\n\n<p>Aloitin opintoni <strong>Stadin ammatti- ja aikuisopistossa</strong> vuonna 2023, ja siitä lähtien olen ollut täysin koukussa. On uskomattoman palkitsevaa nähdä, kun idea muuttuu toimivaksi verkkosivuksi tai -sovellukseksi.</p>\n\n<h2>Mitä olen oppinut tähän mennessä?</h2>\n<p>Opintojeni aikana olen oppinut useita tärkeitä teknologioita:</p>\n<ul>\n<li><strong>HTML5 & CSS3</strong> – verkkosivujen rakenteen ja ulkoasun perusta</li>\n<li><strong>JavaScript</strong> – interaktiivisten elementtien luominen</li>\n<li><strong>PHP & MySQL</strong> – backend-kehitys ja tietokantojen hallinta</li>\n<li><strong>Git & GitHub</strong> – versionhallinta ja yhteistyö</li>\n<li><strong>Responsiivinen suunnittelu</strong> – sivustot toimivat kaikilla laitteilla</li>\n</ul>\n\n<p>Olen myös oppinut, että ohjelmointi on jatkuvaa oppimista – uusia teknologioita ja työkaluja tulee koko ajan, ja se tekee alasta niin kiehtovan!</p>\n\n<h2>Mitä tämä blogi tulee sisältämään?</h2>\n<p>Tässä blogissa aion jakaa:</p>\n<ul>\n<li><strong>Oppimiani asioita</strong> – käytännön vinkkejä ja oivalluksia</li>\n<li><strong>Projektejani</strong> – mitä olen rakentanut ja mitä olen oppinut</li>\n<li><strong>Haasteita ja ratkaisuja</strong> – miten olen ratkaissut ongelmia</li>\n<li><strong>Urapolkua</strong> – miten edistyn verkkokehittäjänä</li>\n<li><strong>Web-kehityksen trendejä</strong> – uusimmat työkalut ja tekniikat</li>\n</ul>\n\n<h2>Lopuksi</h2>\n<p>Olen innoissani tästä uudesta matkasta ja toivon, että blogistani on sinulle hyötyä ja inspiraatiota. Olipa sitten itse aloittelija, kokenut kehittäjä tai joku, joka vain pohtii uraa IT-alalla – toivottavasti löydät täältä kiinnostavaa luettavaa.</p>\n<p>Kiitos, että luit ensimmäisen postaukseni! Jätä kommenttia tai ota yhteyttä, jos haluat keskustella lisää. Seuraavassa postauksessa aion kirjoittaa ensimmäisestä merkittävästä projektistani, jossa rakensin täydellisen kirjastojärjestelmän.</p>\n<p>Pysy kuulolla! 🚀</p>','Tervetuloa blogiini! Tässä ensimmäisessä postauksessa kerron itsestäni, taustastani rakennus- ja sähköalalta, miksi päätin ryhtyä verkkokehittäjäksi ja mitä tämä blogi tulee sisältämään.',NULL,'Web-kehitys',NULL,21,'published','2026-03-21 15:06:46','2026-03-21 13:06:46','2026-03-22 12:24:23'),(3,'Matkani verkkokehittäjäksi – Miten aloitin','matkani-verkkokehitt-j-ksi-miten-aloitin','<h2>Miten kaikki alkoi?</h2>\r\n<p>Hei taas! Tässä toisessa blogipostauksessa haluan jakaa kanssani matkani siitä, miten päädyin verkkokehityksen pariin. Toivottavasti tämä inspiroi muita, jotka harkitsevat alanvaihtoa tai uran aloittamista IT-alalla.</p>\r\n\r\n<h2>Taustani ennen IT-alaa</h2>\r\n<p>Kuten ensimmäisessä postauksessa mainitsin, taustani on rakennus- ja sähköalalta. Työskentelin useita vuosia erilaisissa rakennusprojekteissa, joissa opin tarkkuutta, ongelmanratkaisua ja projektinhallintaa. Nämä taidot ovat osoittautuneet erittäin arvokkaiksi myös ohjelmistokehityksessä.</p>\r\n\r\n<p>Rakennusalalla jokainen virhe voi maksaa paljon, ja samoin on ohjelmoinnissa – pieni bugi voi aiheuttaa suuria ongelmia. Tämä ajattelutapa on auttanut minua kirjoittamaan laadukkaampaa koodia.</p>\r\n\r\n<h2>Miten päädyin ohjelmoinnin pariin?</h2>\r\n<p>Kiinnostus tietotekniikkaan on aina ollut taustalla, mutta vasta muutama vuosi sitten päätin ryhtyä tosissani opiskelemaan. Aloitin tutustumalla ilmaisiin verkkokursseihin ja huomasin nopeasti, että tämä on sitä, mitä haluan tehdä.</p>\r\n\r\n<p>Päätös hakea Stadin ammatti- ja aikuisopistoon oli yksi parhaista päätöksistäni. Siellä olen saanut vahvan perustan ohjelmointiin ja päässyt toteuttamaan mielenkiintoisia projekteja.</p>\r\n\r\n<h2>Ensimmäiset askeleet</h2>\r\n<p>Aluksi opettelin HTML:ää ja CSS:ää. Tuntui mahtavalta nähdä, miten koodi muuttuu näkyväksi verkkosivuksi. Sitten siirryin JavaScriptiin, ja myöhemmin PHP:hen ja MySQL:ään. Jokainen uusi teknologia avasi uusia ovia.</p>\r\n\r\n<p>Haasteita on tullut vastaan, mutta jokainen ratkaistu ongelma on opettanut uutta. Tärkeintä on ollut pitää utelias mieli ja olla valmis oppimaan jatkuvasti.</p>\r\n\r\n<h2>Vinkkejä aloittelijoille</h2>\r\n<ul>\r\n<li><strong>Aloita perusteista</strong> – HTML, CSS ja JavaScript ennen monimutkaisempia teknologioita</li>\r\n<li><strong>Tee omia projekteja</strong> – teoria on tärkeää, mutta käytäntö vie eteenpäin</li>\r\n<li><strong>Älä pelkää virheitä</strong> – ne ovat parhaita opettajia</li>\r\n<li><strong>Käytä versionhallintaa (Git)</strong> – se on alan perustaito</li>\r\n<li><strong>Liity yhteisöihin</strong> – muilta oppii paljon</li>\r\n</ul>\r\n\r\n<h2>Lopuksi</h2>\r\n<p>Uranvaihto ei ole aina helppoa, mutta jos intohimo ja motivaatio ovat kohdillaan, se on täysin mahdollista. Uskon, että jokainen voi oppia ohjelmoimaan, kunhan on valmis tekemään työtä ja oppimaan virheistään.</p>\r\n\r\n<p>Seuraavassa postauksessa aion kertoa tarkemmin ensimmäisestä merkittävästä projektistani. Pysy kuulolla!</p>','Miten kaikki alkoi?\r\nHei taas! Tässä toisessa blogipostauksessa haluan jakaa kanssani matkani siitä, miten päädyin verkkokehityksen pariin. Toivo...',NULL,'Urapolku',NULL,5,'published','2026-03-22 14:13:08','2026-03-22 12:13:08','2026-03-24 10:28:51'),(4,'PHP:n ja MySQL:n oppiminen – Tietokantojen maailmaan','php-ja-mysql-oppiminen-tietokantojen-maailmaan','<h2>PHP ja MySQL – Matkani tietokantojen pariin</h2>\n\n<p>Tervetuloa uuteen blogipostaukseeni! Tällä kertaa haluan jakaa kokemuksiani PHP:n ja MySQL:n opiskelusta. Tämä on ollut yksi tärkeimmistä ja samalla haastavimmista osa-alueista verkkokehityksessä.</p>\n\n<h2>Miksi PHP ja MySQL?</h2>\n\n<p>Kun aloitin verkkokehityksen opiskelun, tiesin, että staattiset HTML- ja CSS-sivut eivät riitä. Halusin rakentaa dynaamisia verkkosovelluksia, joissa käyttäjät voivat kirjautua sisään, tallentaa tietoja ja vuorovaikuttaa sisällön kanssa. Siksi PHP ja MySQL olivat luonnollinen valinta.</p>\n\n<p><strong>PHP</strong> on palvelinpuolen kieli, joka mahdollistaa dynaamisen sisällön luomisen. <strong>MySQL</strong> puolestaan on tietokantajärjestelmä, johon voimme tallentaa kaiken tiedon – käyttäjät, blogipostaukset, kommentit ja paljon muuta.</p>\n\n<h2>Ensimmäiset askeleet</h2>\n\n<p>Aluksi PHP tuntui monimutkaiselta. Muuttujat, funktiot, lohkot, tietokantayhteydet – kaikki oli uutta. Mutta kuten aina, jaksoin opiskella pikkuhiljaa. Aloitin perusteista: miten PHP-koodi upotetaan HTML:n sekaan, miten muuttujia käytetään ja miten luodaan yksinkertaisia funktioita.</p>\n\n<p>MySQL:n kanssa aloitin yksinkertaisilla SELECT-kyselyillä. Oli mahtavaa nähdä, miten tietokannasta haetut tiedot ilmestyvät verkkosivulle!</p>\n\n<h2>Haasteet ja miten selvisin</h2>\n\n<p>Suurin haaste oli ymmärtää, miten PHP ja MySQL toimivat yhdessä. Tietokantayhteyden luominen, SQL-injektioiden estäminen ja tietokantataulujen suunnittelu vaativat paljon harjoittelua.</p>\n\n<p>Opin, että <strong>valmistellut lauseet (prepared statements)</strong> ovat äärimmäisen tärkeitä tietoturvan kannalta. Ne estävät SQL-injektiot ja tekevät koodista turvallisempaa.</p>\n\n<pre><code>\n// Esimerkki prepared statementista\n$stmt = $conn->prepare(\"SELECT * FROM users WHERE email = ?\");\n$stmt->bind_param(\"s\", $email);\n$stmt->execute();\n$result = $stmt->get_result();\n</code></pre>\n\n<h2>Käytännön projekti – Kirjastojärjestelmä</h2>\n\n<p>Suurin projektini tähän mennessä on ollut <strong>kirjastojärjestelmä</strong>. Siinä sain käyttää kaikkea oppimaani:</p>\n\n<ul>\n<li><strong>Käyttäjähallinta</strong> – rekisteröityminen, kirjautuminen, roolit (admin, käyttäjä)</li>\n<li><strong>Kirjojen hallinta</strong> – kirjojen lisääminen, muokkaaminen, poistaminen</li>\n<li><strong>Lainausjärjestelmä</strong> – kirjojen lainaaminen ja palauttaminen</li>\n<li><strong>Varausjärjestelmä</strong> – varaukset ja jonotus</li>\n<li><strong>Laitteiden hallinta</strong> – kannettavat, tabletit ja muut laitteet lainattavaksi</li>\n</ul>\n\n<p>Tämän projektin aikana opin enemmän kuin missään kurssissa. Käytännön tekeminen on todella parasta oppimista!</p>\n\n<h2>Suosikkiresurssini PHP:n ja MySQL:n oppimiseen</h2>\n\n<ul>\n<li><strong>W3Schools</strong> – hyvät perusteet ja esimerkit</li>\n<li><strong>PHP.net</strong> – virallinen dokumentaatio</li>\n<li><strong>Stack Overflow</strong> – ratkaisut ongelmiin</li>\n<li><strong>YouTube</strong> – visuaaliset opetusvideot</li>\n<li><strong>Omat projektit</strong> – paras tapa oppia</li>\n</ul>\n\n<h2>Vinkkejä aloittelijoille</h2>\n\n<p>Jos olet aloittamassa PHP:n ja MySQL:n opiskelua, tässä muutama vinkki:</p>\n\n<ol>\n<li><strong>Aloita perusteista</strong> – opettele PHP:n syntaksi ennen tietokantoja</li>\n<li><strong>Ymmärrä tietokantarakenteet</strong> – miten taulut suunnitellaan oikein</li>\n<li><strong>Käytä valmisteltuja lauseita</strong> – tietoturva on tärkeää!</li>\n<li><strong>Tee omia projekteja</strong> – teoria ei riitä, koodaa itse</li>\n<li><strong>Lue toisten koodia</strong> – GitHub on loistava resurssi</li>\n<li><strong>Älä luovuta</strong> – vaikeatkin asiat selviävät ajan kanssa</li>\n</ol>\n\n<h2>Mitä olen oppinut tähän mennessä?</h2>\n\n<p>Tässä lista tärkeimmistä asioista, jotka olen oppinut PHP:n ja MySQL:n parissa:</p>\n\n<ul>\n<li>PHP:n perussyntaksi (muuttujat, funktiot, lohkot)</li>\n<li>Olio-ohjelmoinnin perusteet PHP:ssä</li>\n<li>Tietokantayhteyden luominen (MySQLi ja PDO)</li>\n<li>SQL-kyselyt (SELECT, INSERT, UPDATE, DELETE)</li>\n<li>Valmistellut lauseet (prepared statements)</li>\n<li>Tietokantataulujen suunnittelu ja normalisointi</li>\n<li>Käyttäjähallinta ja sessiot</li>\n<li>Salasanojen hashaus ja tietoturva</li>\n<li>CRUD-toiminnallisuus (Create, Read, Update, Delete)</li>\n<li>Dynaamisten verkkosovellusten rakentaminen</li>\n</ul>\n\n<h2>Lopuksi</h2>\n\n<p>PHP ja MySQL ovat avain taitavia dynaamisten verkkosovellusten rakentamiseen. Vaikka alku saattaa tuntua haastavalta, jokaisen uuden asian oppiminen on palkitsevaa. Nyt osaan rakentaa täysin toimivia verkkosovelluksia tietokantoineen – ja se on mahtava tunne!</p>\n\n<p>Seuraavassa postauksessa aion kertoa tarkemmin <strong>kirjastojärjestelmäni rakentamisesta</strong> ja siitä, mitä haasteita kohtasin matkan varrella.</p>\n\n<p>Jätä kommentti tai ota yhteyttä, jos haluat keskustella lisää PHP:stä tai MySQL:stä!</p>\n\n<p>Pysy kuulolla! 🚀</p>','Tässä blogipostauksessa jaan kokemuksiani PHP:n ja MySQL:n opiskelusta, haasteista, oppimistani asioista ja vinkeistä muille aloittelijoille.',NULL,'Web-kehitys',NULL,0,'published','2026-03-23 13:13:22','2026-03-23 11:13:22','2026-03-23 11:13:22'),(5,'Matkani verkkokehittäjäksi – Kattava kertomus alanvaihdosta ja oppimisesta','matkani-verkkokehittajaksi-kattava-kertomus','<h2>Johdanto – Miksi päätin vaihtaa alaa?</h2>\n\n<p>Hei ja tervetuloa tähän kattavaan blogipostaukseen! Tässä postauksessa haluan jakaa koko tarinani – miten päädyin rakennusalalta IT-alalle, mitä haasteita kohtasin ja mitä olen oppinut matkan varrella. Toivottavasti tämä inspiroi muita, jotka harkitsevat alanvaihtoa tai vasta aloittavat ohjelmoinnin opiskelua.</p>\n\n<p>Olen <strong>Aziz Rahman Noyan</strong>, 30-vuotias verkkokehittäjä Vantaalta. Taustani on kuitenkin hyvin erilainen kuin monella muulla alalla työskentelevällä. Ennen IT-uraa työskentelin rakennus- ja sähköalalla useita vuosia. Tämä tausta on antanut minulle vahvan perustan tekniseen ajatteluun, ongelmanratkaisuun ja tarkkuuteen – kaikki taitoja, joista on valtavasti hyötyä ohjelmointityössä.</p>\n\n<h2>Rakennusalalta IT-alalle – Miten kaikki alkoi?</h2>\n\n<p>Kiinnostukseni tietotekniikkaan on aina ollut vahva, mutta vasta muutama vuosi sitten päätin ryhtyä tosissani opiskelemaan ohjelmointia. Rakennus- ja sähköalalla opin, miten tärkeää on ymmärtää kokonaisuuksia ja nähdä asioiden taustalla olevat periaatteet. Samalla tavalla verkkokehityksessä on kyse kokonaisuuksien hallinnasta – miten etupää (frontend), tietokannat ja palvelin (backend) toimivat saumattomasti yhdessä.</p>\n\n<p>Aloitin opintoni <strong>Stadin ammatti- ja aikuisopistossa</strong> vuonna 2023, ja siitä lähtien olen ollut täysin koukussa. On uskomattoman palkitsevaa nähdä, kun idea muuttuu toimivaksi verkkosivuksi tai -sovellukseksi.</p>\n\n<h2>Mitä olen oppinut tähän mennessä?</h2>\n\n<p>Opintojeni aikana olen oppinut useita tärkeitä teknologioita ja käsitteitä:</p>\n\n<ul>\n<li><strong>HTML5 & CSS3</strong> – verkkosivujen rakenteen ja ulkoasun perusta. Olen oppinut luomaan responsiivisia sivustoja, jotka toimivat kaikilla laitteilla.</li>\n<li><strong>JavaScript</strong> – interaktiivisten elementtien luominen ja dynaamisuus. Olen oppinut manipuloimaan DOM:ia, käyttämään tapahtumia ja tekemään AJAX-pyyntöjä.</li>\n<li><strong>PHP & MySQL</strong> – backend-kehitys, tietokantojen hallinta ja dynaamiset verkkosivut. Olen rakentanut täydellisiä kirjautumisjärjestelmiä, CRUD-sovelluksia ja tietokantayhteyksiä.</li>\n<li><strong>Git & GitHub</strong> – versionhallinta ja yhteistyö muiden kehittäjien kanssa. Osaan hallita haaroja, tehdä committeja ja ratkaista konflikteja.</li>\n<li><strong>Responsiivinen suunnittelu</strong> – sivustot toimivat kaikilla laitteilla (mobiili, tabletti, tietokone). Olen oppinut käyttämään media queryja ja flexbox/grid-layoutia.</li>\n<li><strong>jQuery & AJAX</strong> – dynaamisen sisällön lataaminen ilman sivun uudelleenlatausta.</li>\n<li><strong>JSON & API:t</strong> – ulkoisten palveluiden integrointi ja datan käsittely.</li>\n<li><strong>SQL & tietokantasuunnittelu</strong> – tietokantataulujen suunnittelu, normalisointi ja monimutkaiset kyselyt.</li>\n<li><strong>Tietoturva</strong> – SQL-injektioiden estäminen, salasanojen hashaus, XSS-suojaus.</li>\n</ul>\n\n<h2>Ensimmäinen suuri projektini – Kirjastojärjestelmä</h2>\n\n<p>Suurin projektini tähän mennessä on ollut <strong>täydellinen kirjastojärjestelmä</strong>. Tässä projektissa sain käyttää kaikkea oppimaani:</p>\n\n<ul>\n<li><strong>Käyttäjähallinta</strong> – rekisteröityminen, kirjautuminen, istunnot, roolit (admin, manager, käyttäjä).</li>\n<li><strong>Kirjojen hallinta</strong> – kirjojen lisääminen, muokkaaminen, poistaminen, hakutoiminto, kategoriat.</li>\n<li><strong>Lainausjärjestelmä</strong> – kirjojen lainaaminen ja palauttaminen, eräpäivät, myöhästymismaksut.</li>\n<li><strong>Varausjärjestelmä</strong> – varaukset, jonotus, varauksen peruminen.</li>\n<li><strong>Laitteiden hallinta</strong> – kannettavat, tabletit ja muut laitteet lainattavaksi.</li>\n<li><strong>Admin-paneeli</strong> – täydellinen hallintapaneeli sisällön hallintaan.</li>\n<li><strong>Blogi</strong> – dynaaminen blogi tietokannalla.</li>\n<li><strong>Profiilit ja kuvat</strong> – käyttäjäprofiilit, profiilikuvien lataus.</li>\n<li><strong>Raportit</strong> – lainaustilastot, suosituimmat kirjat, käyttäjäaktiivisuus.</li>\n</ul>\n\n<p>Tämä projekti kesti useita viikkoja, ja opin enemmän kuin missään kurssissa. Käytännön tekeminen on todella parasta oppimista! Jokainen bugi ja ongelma opetti minulle jotain uutta.</p>\n\n<h2>Haasteet ja miten selvisin niistä</h2>\n\n<p>Matka ei ole ollut pelkästään helppo. Alussa tuntui, että tietoa on liikaa ja kaikki on vaikeaa. Mutta olen oppinut, että <strong>tärkeintä on jakaa isot ongelmat pienempiin osiin</strong> ja ratkaista ne yksi kerrallaan.</p>\n\n<p>Kun en ymmärrä jotain asiaa, etsin tietoa, katson videoita, kysyn apua opettajilta ja muilta opiskelijoilta. Yhteisö on tärkeä – kukaan ei opi kaikkea yksin.</p>\n\n<p>Toinen tärkeä oppi on ollut <strong>virheistä oppiminen</strong>. Koodatessa virheitä tulee koko ajan, ja se on ihan normaalia. Jokainen bugi on mahdollisuus oppia jotain uutta.</p>\n\n<h2>Suosikkiresurssini oppimiseen</h2>\n\n<ul>\n<li><strong>W3Schools</strong> – hyvät perusteet ja esimerkit kaikille teknologioille.</li>\n<li><strong>PHP.net</strong> – virallinen dokumentaatio, erittäin hyödyllinen.</li>\n<li><strong>MDN Web Docs</strong> – paras lähde JavaScriptille ja web-standardeille.</li>\n<li><strong>Stack Overflow</strong> – ratkaisut ongelmiin, miljoonia kysymyksiä ja vastauksia.</li>\n<li><strong>YouTube</strong> – visuaaliset opetusvideot, erityisesti kanavat kuten Traversy Media, FreeCodeCamp.</li>\n<li><strong>Omat projektit</strong> – paras tapa oppia, rakentamalla oppii parhaiten.</li>\n<li><strong>GitHub</strong> – muiden koodin lukeminen ja omien projektien jakaminen.</li>\n<li><strong>ChatGPT / AI-työkalut</strong> – avuksi ongelmanratkaisuun ja ideointiin.</li>\n</ul>\n\n<h2>Vinkkejä aloittelijoille</h2>\n\n<p>Jos olet vasta aloittamassa ohjelmoinnin opiskelua tai harkitset alanvaihtoa, tässä muutama vinkki, jotka ovat auttaneet minua:</p>\n\n<ol>\n<li><strong>🌱 Aloita perusteista</strong> – opettele HTML, CSS ja JavaScript ennen monimutkaisempia teknologioita. Hyvät perusteet kantavat pitkälle.</li>\n<li><strong>💪 Tee omia projekteja</strong> – teoria on tärkeää, mutta käytäntö vie eteenpäin. Rakenna jotain, mikä kiinnostaa sinua.</li>\n<li><strong>❌ Älä pelkää virheitä</strong> – ne ovat parhaita opettajia. Jokainen virhe on askel eteenpäin.</li>\n<li><strong>📚 Käytä versionhallintaa (Git)</strong> – se on alan perustaito, ja se kannattaa opetella heti alussa.</li>\n<li><strong>🤝 Liity yhteisöihin</strong> – muilta oppii paljon. Kysy, keskustele ja jaa omaa osaamistasi.</li>\n<li><strong>📖 Pidä oppimispäiväkirjaa</strong> – kirjoita ylös, mitä olet oppinut. Se auttaa kertaamaan ja näkemään edistymisen.</li>\n<li><strong>⏰ Ole kärsivällinen</strong> – oppiminen vie aikaa. Älä vertaa itseäsi muihin, vaan keskity omaan kehitykseesi.</li>\n<li><strong>🎯 Aseta tavoitteita</strong> – pienet tavoitteet auttavat pysymään motivoituneena.</li>\n<li><strong>💡 Koodaa joka päivä</strong> – jopa 30 minuuttia päivässä on parempi kuin 5 tuntia viikonloppuna.</li>\n<li><strong>🔍 Lue toisten koodia</strong> – GitHub on loistava paikka oppia uusia tekniikoita.</li>\n</ol>\n\n<h2>Mitä olen oppinut itsestäni?</h2>\n\n<p>Tämä matka on opettanut minulle paljon itsestäni. Olen huomannut, että olen:</p>\n\n<ul>\n<li><strong>Utelias</strong> – haluan aina oppia uutta ja ymmärtää, miten asiat toimivat.</li>\n<li><strong>Pitkäjännitteinen</strong> – osaan keskittyä ja työskennellä ongelmien parissa, kunnes ne ratkeavat.</li>\n<li><strong>Ongelmanratkaisija</strong> – rakennusalan tausta on kehittänyt tätä taitoa.</li>\n<li><strong>Tiimipelaaja</strong> – nautin yhteistyöstä ja oppimisesta muiden kanssa.</li>\n<li><strong>Jatkuva oppija</strong> – ymmärrän, että alalla on aina uutta opittavaa.</li>\n</ul>\n\n<h2>Mitä seuraavaksi?</h2>\n\n<p>Tulevaisuudessa aion:</p>\n\n<ul>\n<li>Syventää osaamistani <strong>Reactissa</strong> ja <strong>Next.js:ssä</strong>.</li>\n<li>Oppia <strong>TypeScriptiä</strong> – se tekee koodista turvallisempaa.</li>\n<li>Tutustua <strong>Node.js:ään</strong> ja backend-kehitykseen.</li>\n<li>Oppia <strong>Dockerin</strong> ja konttiteknologiat.</li>\n<li>Rakentaa <strong>mobiilisovelluksia</strong> React Nativella.</li>\n<li>Osallistua <strong>työharjoitteluun</strong> ja saada ensimmäinen työpaikka IT-alalta.</li>\n</ul>\n\n<h2>Lopuksi – Kiitos, että luit!</h2>\n\n<p>Olen innoissani tästä uudesta matkasta ja siitä, että pääsen jakamaan sitä kanssasi. Toivon, että blogistani on sinulle hyötyä, olitpa sitten aloittelija, kokenut kehittäjä tai joku, joka vain pohtii uraa IT-alalla.</p>\n\n<p>Jos sinulla on kysyttävää, ideoita blogin aiheiksi tai haluat vain vaihtaa ajatuksia, ota rohkeasti yhteyttä. Jätä kommentti tai laita viestiä – kuulen mielelläni sinusta!</p>\n\n<p><strong>Muista: jokainen suuri kehittäjä on ollut joskus aloittelija. Tärkeintä on aloittaa ja jatkaa eteenpäin!</strong> 🚀</p>\n\n<p>Pysy kuulolla – seuraavassa postauksessa aion kertoa tarkemmin <strong>kirjastojärjestelmäni teknisistä yksityiskohdista</strong> ja siitä, mitä haasteita kohtasin matkan varrella.</p>','Tässä kattavassa blogipostauksessa jaan koko tarinani rakennusalalta IT-alalle, mitä olen oppinut, kohtaamiani haasteita ja vinkkejä muille alanvaihtajille.',NULL,'Urapolku',NULL,1,'published','2026-03-25 12:58:59','2026-03-25 10:58:59','2026-03-25 10:59:15');
/*!40000 ALTER TABLE `blog_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certifications`
--

DROP TABLE IF EXISTS `certifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `issuer` varchar(200) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `credential_url` varchar(500) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certifications`
--

LOCK TABLES `certifications` WRITE;
/*!40000 ALTER TABLE `certifications` DISABLE KEYS */;
INSERT INTO `certifications` VALUES (1,'Full Stack Web Development','Stadin ammattiopisto','2024-06-15',NULL,NULL,'https://cdn-icons-png.flaticon.com/512/919/919825.png',1,'2026-03-23 10:11:41'),(2,'PHP & MySQL Advanced','Online Course','2024-03-10',NULL,NULL,'https://cdn-icons-png.flaticon.com/512/919/919830.png',2,'2026-03-23 10:11:41'),(3,'JavaScript Modern Features','FreeCodeCamp','2023-12-20',NULL,NULL,'https://cdn-icons-png.flaticon.com/512/5968/5968292.png',3,'2026-03-23 10:11:41'),(4,'Responsive Web Design','freeCodeCamp','2024-01-20',NULL,NULL,'https://cdn-icons-png.flaticon.com/512/1055/1055687.png',4,'2026-03-24 11:11:22'),(5,'Git & GitHub Mastery','GitHub Learning Lab','2024-02-15',NULL,NULL,'https://cdn-icons-png.flaticon.com/512/2111/2111288.png',5,'2026-03-24 11:11:22'),(6,'Python Programming Basics','University of Helsinki','2023-11-10',NULL,NULL,'https://cdn-icons-png.flaticon.com/512/5968/5968350.png',6,'2026-03-24 11:11:22'),(7,'Database Design & SQL','Tech Academy','2024-04-05',NULL,NULL,'https://cdn-icons-png.flaticon.com/512/919/919836.png',7,'2026-03-24 11:11:22');
/*!40000 ALTER TABLE `certifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `reply` text,
  `reply_date` datetime DEFAULT NULL,
  `status` enum('unread','read','replied') DEFAULT 'unread',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_messages`
--

LOCK TABLES `contact_messages` WRITE;
/*!40000 ALTER TABLE `contact_messages` DISABLE KEYS */;
INSERT INTO `contact_messages` VALUES (1,'Aziz','matiasmasih@gmail.com','Create a website','i want to create me  and website for my hotel',NULL,NULL,'unread','2026-03-23 10:05:31'),(2,'Aziz','matiasmasih@gmail.com','Emergency Case','can you make me a websites','yes why not i am ready','2026-03-23 12:07:22','replied','2026-03-23 10:06:30');
/*!40000 ALTER TABLE `contact_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `education`
--

DROP TABLE IF EXISTS `education`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `education` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `institution` varchar(200) DEFAULT NULL,
  `period` varchar(100) DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fa-graduation-cap',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `education`
--

LOCK TABLES `education` WRITE;
/*!40000 ALTER TABLE `education` DISABLE KEYS */;
INSERT INTO `education` VALUES (1,'Tieto- ja viestintätekniikka','Stadin ammatti- ja aikuisopisto','2023-2025','fa-graduation-cap',1,'2026-03-20 11:51:58'),(2,'Peruskoulutus','Eiran aikuislukio','2020-2022','fa-graduation-cap',2,'2026-03-20 11:51:58'),(3,'Suomen kielen opinnot','Ryttylän kansanopisto','2019','fa-graduation-cap',3,'2026-03-20 11:51:58'),(4,'Peruskoulu','Afganistan','2005','fa-graduation-cap',4,'2026-03-20 11:51:58');
/*!40000 ALTER TABLE `education` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `experience`
--

DROP TABLE IF EXISTS `experience`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `experience` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `company` varchar(200) DEFAULT NULL,
  `period` varchar(100) DEFAULT NULL,
  `description` text,
  `icon` varchar(50) DEFAULT 'fa-laptop-code',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `experience`
--

LOCK TABLES `experience` WRITE;
/*!40000 ALTER TABLE `experience` DISABLE KEYS */;
INSERT INTO `experience` VALUES (1,'Verkkosivujen ja -kauppojen optimointi','Medigoo Oy, Espoo','2024 (7 kuukautta)',NULL,'fa-laptop-code',1,'2026-03-20 11:52:40'),(2,'Elintarvikepakkaaja','Finsect Oy','2022',NULL,'fa-laptop-code',2,'2026-03-20 11:52:40'),(3,'Pesula-avustaja','Omnia','2021 (1 kuukausi)',NULL,'fa-laptop-code',3,'2026-03-20 11:52:40'),(4,'Harjoittelu','Lidl','2022 (2 viikkoa)',NULL,'fa-laptop-code',4,'2026-03-20 11:52:40'),(5,'Rakennus- ja sähkötyöt','Kantrak, Amran, Ghaznavi','2011-2014',NULL,'fa-laptop-code',5,'2026-03-20 11:52:40');
/*!40000 ALTER TABLE `experience` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_info`
--

DROP TABLE IF EXISTS `personal_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_info` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `title` varchar(100) NOT NULL,
  `bio` text,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `about_image` varchar(255) DEFAULT NULL,
  `github` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `whatsapp` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `skype` varchar(255) DEFAULT NULL,
  `teams` varchar(255) DEFAULT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_info`
--

LOCK TABLES `personal_info` WRITE;
/*!40000 ALTER TABLE `personal_info` DISABLE KEYS */;
INSERT INTO `personal_info` VALUES (1,'Aziz Rahman Noyan','Verkkokehittäjä','Olen 30-vuotias ammattilainen, jolla on vahva tausta sähkö- ja rakennusalalta, mikä on kehittänyt ongelmanratkaisutaitojani ja teknistä osaamistani.','matiasmasih@gmail.com','+358 41 311 4312','Vaahtokuja 5 E50','1994-12-31','Vantaa, Suomi','uploads/profile.jpg','uploads/profile.jpg','https://github.com/matiasmasih','https://www.linkedin.com/in/matiasmasih','https://wa.me/+358413114312','https://www.facebook.com/matiasmasih','skype:matiasmasih?call','https://teams.live.com/v2','https://twitter.com','2026-03-20 11:51:08');
/*!40000 ALTER TABLE `personal_info` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `category` varchar(50) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `project_url` varchar(500) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES (1,'Pankki HTML','web','https://images.unsplash.com/photo-1517694712202-14dd9538aa97','https://github.com/matiasmasih/projects-/blob/main/Banking%20HTML/BANK.HTML/banking.html',NULL,1,'2026-03-20 11:54:00'),(2,'Analoginen kello','web','https://images.unsplash.com/photo-1498050108023-c5249f4df085','https://github.com/matiasmasih/projects-/tree/main/Analog%20clock',NULL,2,'2026-03-20 11:54:00'),(3,'Kalenteri Pythonilla','python','https://images.unsplash.com/photo-1542831371-29b0f74f9713','https://github.com/matiasmasih/projects-/blob/main/Calendar/Calendar.py',NULL,3,'2026-03-20 11:54:00');
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resume_files`
--

DROP TABLE IF EXISTS `resume_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resume_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int DEFAULT NULL,
  `language` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `download_count` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resume_files`
--

LOCK TABLES `resume_files` WRITE;
/*!40000 ALTER TABLE `resume_files` DISABLE KEYS */;
INSERT INTO `resume_files` VALUES (1,'Aziz Rahman Noyan CV','uploads/cv/Aziz_Rahman_Noyan_CV.pdf',NULL,'Finnish',1,0,'2026-03-21 11:39:07');
/*!40000 ALTER TABLE `resume_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('text','textarea','color','image','boolean') DEFAULT 'text',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES (1,'site_title','Aziz Rahman Noyan | Portfolio','text','2026-03-20 12:00:44','2026-03-20 12:00:44'),(2,'site_description','Verkkokehittäjän portfolio','textarea','2026-03-20 12:00:44','2026-03-20 12:00:44'),(3,'primary_color','#00e5ff','color','2026-03-20 12:00:44','2026-03-20 12:00:44'),(4,'secondary_color','#8a2be2','color','2026-03-20 12:00:44','2026-03-20 12:00:44'),(5,'footer_text','© 2025 Aziz Rahman Noyan. Kaikki oikeudet pidätetään.','text','2026-03-20 12:00:44','2026-03-20 12:00:44'),(6,'contact_email','matiasmasih@gmail.com','text','2026-03-20 12:00:44','2026-03-20 12:00:44'),(7,'contact_phone','+358 41 311 4312','text','2026-03-20 12:00:44','2026-03-20 12:00:44'),(8,'contact_address','Vaahtokuja 5 E50, Vantaa, Suomi','text','2026-03-20 12:00:44','2026-03-20 12:00:44');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `skills`
--

DROP TABLE IF EXISTS `skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `skills` (
  `id` int NOT NULL AUTO_INCREMENT,
  `skill_name` varchar(100) NOT NULL,
  `percentage` int DEFAULT '0',
  `category` varchar(50) DEFAULT NULL,
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `skills`
--

LOCK TABLES `skills` WRITE;
/*!40000 ALTER TABLE `skills` DISABLE KEYS */;
INSERT INTO `skills` VALUES (1,'Dari',100,'language',1,'2026-03-20 11:53:23'),(2,'Farsi',90,'language',2,'2026-03-20 11:53:23'),(3,'Suomi',60,'language',3,'2026-03-20 11:53:23'),(4,'Englanti',40,'language',4,'2026-03-20 11:53:23'),(5,'HTML',40,'programming',5,'2026-03-20 11:53:23'),(6,'CSS',35,'programming',6,'2026-03-20 11:53:23'),(7,'JavaScript',10,'programming',7,'2026-03-20 11:53:23'),(8,'Python',25,'programming',8,'2026-03-20 11:53:23');
/*!40000 ALTER TABLE `skills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `social_links`
--

DROP TABLE IF EXISTS `social_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_links` (
  `id` int NOT NULL AUTO_INCREMENT,
  `platform` varchar(50) NOT NULL,
  `url` varchar(500) NOT NULL,
  `icon_class` varchar(100) DEFAULT NULL,
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `social_links`
--

LOCK TABLES `social_links` WRITE;
/*!40000 ALTER TABLE `social_links` DISABLE KEYS */;
INSERT INTO `social_links` VALUES (1,'Teams','https://teams.live.com/v2','fab fa-microsoft',1,'2026-03-20 11:54:43'),(2,'WhatsApp','https://wa.me/+358413114312','fab fa-whatsapp',2,'2026-03-20 11:54:43'),(3,'GitHub','https://github.com/matiasmasih','fab fa-github',3,'2026-03-20 11:54:43'),(4,'LinkedIn','https://www.linkedin.com/feed/','fab fa-linkedin-in',4,'2026-03-20 11:54:43');
/*!40000 ALTER TABLE `social_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `testimonials`
--

DROP TABLE IF EXISTS `testimonials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `testimonials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_name` varchar(100) NOT NULL,
  `client_position` varchar(100) DEFAULT NULL,
  `client_image` varchar(255) DEFAULT NULL,
  `testimonial` text NOT NULL,
  `rating` int DEFAULT '5',
  `is_visible` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `testimonials`
--

LOCK TABLES `testimonials` WRITE;
/*!40000 ALTER TABLE `testimonials` DISABLE KEYS */;
INSERT INTO `testimonials` VALUES (1,'Maria Laine','CEO, Tech Solutions Oy',NULL,'Aziz teki meille upean verkkosivuston. Työ oli ammattitaitoista, aikataulussa pysyttiin ja lopputulos ylitti odotukset! Suosittelen lämpimästi.',5,1,1,'2026-03-23 10:57:55'),(2,'Jukka Virtanen','Projektipäällikkö, DigiWorks',NULL,'Erinomaista yhteistyötä! Aziz ymmärsi tarpeemme ja toteutti juuri sellaisen ratkaisun kuin halusimme. Tekninen osaaminen on huippuluokkaa.',5,1,2,'2026-03-23 10:57:55'),(3,'Sanna Mäkelä','Yrittäjä, Sanna Design',NULL,'Olen erittäin tyytyväinen Azizin työhön. Hän rakensi minulle modernin ja helppokäyttöisen verkkokaupan. Tukea on saanut aina tarvittaessa.',4,1,3,'2026-03-23 10:57:55'),(4,'Petri Korhonen','Markkinointipäällikkö, MediaHouse',NULL,'Ammattitaitoinen ja luotettava kumppani. Aziz auttoi meitä monimutkaisen verkkosovelluksen kanssa ja ratkaisi ongelmat nopeasti. Hieno asenne!',5,1,4,'2026-03-23 10:57:55'),(5,'Liisa Heikkinen','Toimitusjohtaja, Luova Studio',NULL,'Azizin kanssa on ilo työskennellä. Hän on innostunut, osaava ja pitää kiinni sovituista aikatauluista. Verkkosivumme on nyt parempi kuin koskaan.',4,1,5,'2026-03-23 10:57:55');
/*!40000 ALTER TABLE `testimonials` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-24 15:24:46
