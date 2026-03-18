<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $tenants = DB::table('tenants')->get(['id']);
        $now = now();

        $articles = [
            [
                'title' => 'Basic Prinzipien im STOTT Pilates',
                'slug' => '/basic-prinzipien-im-stott-pilates',
                'date' => '2024-05-16',
                'image_name' => 'IMG_1977-4-e1715856081431.jpg',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2024/05/IMG_1977-4-e1715856081431.jpg',
                'body' => '<h2>Pilates Grundprinzipien, was bedeutet das eigentlich?</h2>
<p>Die Stärke der Pilates-Methode liegt in der Verbindung mit ihren grundlegenden Prinzipien. Zum einen sind es die Pilatesübungen, aber vor allem ist es die Art und Weise, wie diese ausgeführt werden – das macht die Übungen erst richtig wirksam.</p>
<p>Die ursprüngliche Technik, die von Joseph H. Pilates entwickelt wurde, enthielt sechs bewegungsbasierte Prinzipien, die den Rahmen bildeten. STOTT PILATES®, bekannt als ein &#8222;zeitgenössischer Ansatz&#8220;, entwickelte und integrierte die STOTT PILATES Prinzipien, die mehr auf der Trainingswissenschaft basieren, um die Übungen zu lehren und auszuführen.</p>
<p><strong>Die STOTT PILATES Prinzipien bringen Körperbewusstsein und Fokus in das heutige Training.</strong><br />
Hier stehen eine optimale Ausrichtung und eine saubere Ausführung, in Verbindung mit dem Atem im Vordergrund. Eine optimale Ausrichtung, meint hier nicht, eine einzige und perfekte Ausrichtung. Unsere Körper sind verschieden, und was für den einen angemessen ist, muss nicht gleich für einen anderen passen. Deshalb geht es vor allem darum, eine günstige Ausrichtung des eigenen Körpers zu unterstützen, um optimale Funktionalität zu erreichen.</p>

<h2>1. Dreidimensionale Atmung</h2>
<p>Bei Kindern ist die dreidimensionale Zwerchfell–Atmung noch gut zu beobachten. Leider wird diese im Erwachsenenalter verlernt, was sich negativ auf die Haltung und auf den Spannungszustand in Bauch–, Rücken– und Beckenbodenmuskulatur auswirkt. Folge dessen ändert sich unter anderem das allgemeine Bewegungsverhalten, da es durch die verloren gegangene Atemdynamik den Rückfluss des Blutes zum Herzen verringert und die inneren Organe nicht sorgfältig durchblutet werden. <strong>Fazit:</strong> Man fühlt sich müde und schlapp und die Bewegungen sind meist schwerfällig und unökonomisch, oft sind diese auch schmerzhaft.</p>
<p><b>Was versteht man unter einer dreidimensionalen Atmung?<br />
</b>Man kann sich bei der dreidimensionalen Atmung den Brustkorb wie einen Regenschirm vorstellen, welcher sich mit der Einatmung aktiv von oben nach unten, nach vorne und hinten und zur Seite ausdehnt und mit der Ausatmung wieder aktiv zusammenzieht. Die Bauchdecke bewegt sich zwar leicht mit der Ein– und Ausatmung mit, verliert jedoch nicht ganz die Aktivierung — sie dehnt sich somit nicht ganz aus und erschlafft auch nicht. Der Atemfluss schafft so Raum gegen den Widerstand der Bauchdecke.</p>
<p><b>Warum wird beim Pilates Training die dreidimensionale Atmung eingesetzt?<br />
</b>Die dreidimensionale Atmung hat sich als sehr funktionelle Atmung herausgestellt, deren positive Auswirkung auf Körper und Geist direkt spürbar und in der Fachliteratur festgehalten ist.</p>
<p><strong>Die Gründe dafür liegen darin:</strong></p>
<ul>
<li>Eine entspannte und tiefe Atmung hilft zu fokussieren und sich auf jede Aufgabe zu konzentrieren.</li>
<li>Durch die Nutzung des Zwerchfells und anderen Atemmuskeln wird der innere Druck reguliert. Diese Regulation führt dazu, dass die Stabilität zwischen dem Brustkorb und Becken gehalten wird. Gelenke der Wirbelsäule, die Bandscheiben und der Beckenboden werden nicht belastet.</li>
<li>Das richtige Atmen und die Aktivierung der tiefen Bauchmuskeln unterstützen die dynamische Stabilisierung von Rumpf und Wirbelsäule während des Übens. Die oberflächlichen Muskeln können ihre primäre Aufgabe durchführen, den Rumpf zu bewegen. </li>
<li>Über die 3D–Atmung wird die Mobilität im Brustkorb unterstützt. Neben der Stabilität unterstützt dies auch die Entwicklung der Flexibilität von Wirbelsäule, Becken und Rumpf.</li>
<li>Durch das Zusammenspiel zwischen Beckenboden und Zwerchfell entsteht Bewegung der Organe in ihrem freien Raum. Die Verdauung wird angeregt.</li>
<li>Das führt zu einer allgemeinen Stressreduktion, da das parasympathische Nervensystem aktiviert wird. Die Herzfrequenz sinkt und im besten Fall auch der Blutdruck. Das entspannt und vermeidet unnötige Anspannung in den Muskeln. Es kommt weniger zur Muskelermüdung, da auch die Regeneration gefördert wird.</li>
</ul>

<h2>2. Beckenposition</h2>
<p>Wir legen großen Wert auf die Ausrichtung und Stabilisierung des Beckens und der Lendenwirbelsäule in verschiedenen Positionen. Zwei Positionen, die am häufigsten verwendet werden, um Stabilität zu erreichen, sind die neutrale Position und die Imprint-Position.</p>
<p><b>Neutrale Position<br />
</b>In der neutralen Position ist die Lendenwirbelsäule natürlich, leicht konvex nach vorne, gekrümmt. In den meisten Fällen ist in der Rückenlage das Dreieck aus den vorderen oberen Darmbeinstacheln und der Schambeinfuge parallel zur Matte. Dies ist die stabilste und am besten stoßdämpfende Position und eine gute Lage, um effiziente Bewegungsmuster zu fördern. Neutral bedeutet also, wenn keine Krümmung oder Streckung der Lendenwirbelsäule vorliegt.</p>
<p>Manchmal wird das Becken seitlich gekippt oder die Wirbelsäule seitlich gebeugt oder gedreht, aber wir bezeichnen Wirbelsäule und Becken weiterhin als neutral, wenn sie in der Sagittalebene neutral sind.</p>
<p><b>Imprint Position<br />
</b>Die Imprint-Position bezeichnet ein leichtes Kippen des Beckens nach hinten mit einer leichten Rundung der Lendenwirbelsäule. Die normale Kurve der Lendenwirbelsäule wird durch die Krümmung verlängert, indem die schrägen Bauchmuskeln angespannt werden, um das Becken vorne in Richtung Rippenbogen zu bewegen. In der Rückenlage ist dann das Schambein etwas höher als die Darmbeinstachel.</p>
<p><strong>Die Gründe für eine Imprint Position, liegen darin:</strong></p>
<ul>
<li>Die Imprint-Haltung sollte verwendet werden, um das Becken und die Lendenwirbelsäule zu stabilisieren, wenn die neutrale Ausrichtung nicht stabilisiert werden kann. Wenn die Last (z.B. Beinhebel) größer ist als die Kraft der Bauchmuskeln, bietet zusätzlich eine verkürzte Position (Bein beugen) den mechanischen Vorteil, dass die Spannung gehalten und die Becken-Lenden-Region stabilisiert werden kann. Von Vorteil bei verstärkter Lordose. Im Idealfall sollten die Gliedmaßen sogar sicher auf der Matte abgelegt werden oder anderweitig in eine geschlossene kinematische Kette gebracht werden.</li>
<li>Die Ausführung einer Übung in offener kinematischer Kette, mit Becken und LWS in der Imprint-Position erhöht die Stabilität. Nur wenn genügend Kraft über die Bauchmuskeln für die Stabilisierung entwickelt wurde, sollte die neutrale Haltung in einer offenen kinematischen Kette verwendet werden.</li>
</ul>
<p>Betrachten wir die Beckenposition, sollte das Verhältnis des Beckens zur Lendenwirbelsäule und den Hüftgelenken berücksichtigt werden.</p>

<h2>3. Brustkorbposition</h2>
<p>Da die Bauchmuskeln an den unteren Rippen befestigt sind, müssen sie angespannt werden, um Brustkorb und Brustwirbelsäule korrekt auszurichten. Oft hebt sich der Brustkorb in der Rückenlage an oder senkt sich im Sitzen nach vorne, wodurch die Brustwirbelsäule verlängert wird. Achten Sie besonders beim Einatmen oder bei Armbewegungen über Kopf darauf.</p>
<p><b>Das Atmungsprinzip unterstützt die Ausrichtung<br />
</b>Wenn Sie die beim Atmungsprinzip beschriebenen Muster verwenden und die Bauchspannung stets erhalten, unterstützt dies die Kontrolle der Brustkorbausrichtung. Stelle Dir in der neutralen Rückenlage ein Gewicht vor, das Deine Rippen sanft auf die Matte drückt</p>
<p>Der Brustkorb sollte sich nicht von der Matte heben und auch nicht in die Matte gepresst werden. Betone die Atmung in den hinteren, seitlichen und vorderen Brustkorb und den Oberbauch bei jeder Einatmung. Lass die beiden Seiten des Brustkorbs sich beim Ausatmen näher zueinander bewegen. Der Brustkorb schließt sich bei der Ausatmung natürlicherweise nach innen und unten, während sich die Wirbelsäule leicht beugt. Daher wird die Brustkorbkrümmung meist bei der Ausatmung durchgeführt.</p>

<h2>4. Schulterblattbewegung und Stabilisierung</h2>
<p>Die Stabilisation der Schulterblätter am Brustkorb ist extrem wichtig, sie dient sowohl als Anker für die Arme als auch als Stütze für die Halswirbelsäule. Wenn sie nicht stabilisiert werden, neigt man dazu, die Muskeln in Hals und Schultern zu überanstrengen.</p>
<p><strong>Achte stets auf die Schulterblattstabilisierung</strong></p>
<ul>
<li>bei neutraler Wirbelsäule und Armen in Ruheposition,</li>
<li>beim Beugen oder Strecken der Wirbelsäule,</li>
<li>bei Bewegungen der Arme in jede Richtung.</li>
</ul>
<p><b>Das Schulterblatt erweitert den Bewegungsradius der Arme<br /></b>Da es keine direkte Knochenverbindung zum Brustkorb und zur Wirbelsäule gibt, sind die Schulterblätter sehr beweglich. Als Erweiterung des Bewegungsradius der Arme können sich die Schulterblätter nach oben (Elevation), nach unten (Depression), nach innen (Retraktion) und nach außen (Protraktion) bewegen, nach oben oder unten rotieren oder eine Kombination dieser Bewegungen ausführen.</p></p>

<h2>5. Kopf und Halsposition</h2>
<p>Die Halswirbelsäule sollte stets ihre natürliche Krümmung beibehalten, und der Kopf sollte in vertikaler Haltung direkt über den Schultern balancieren. Dieses Verhältnis sollte auch in allen anderen Anfangspositionen beibehalten werden.</p>
<p><b>Die Halswirbelsäule eine Verlängerung der restlichen Wirbelsäule<br />
</b>Die Halswirbelsäule sollte in den meisten Fällen, die von der Brustwirbelsäule gebildete Linie beim Beugen, Strecken, Drehen und Seitwärtsneigen fortführen. Übe die Stellung der HWS, bevor du in eine Beugung oder Streckung der Wirbelsäule gehst.</p>
<p><strong>Achte stets auf eine gute Ausrichtung der Halswirbelsäule</strong></p>
<ul>
<li>Die Kurve von Kopf- und Halswirbelsäule sollte immer integriert werden, wenn die Brustwirbelsäule gerundet wird. Wenn der Oberkörper aus der Rückenlage gekrümmt wird, achte darauf, die Krümmung mehr im Brustkorb als im Halsbereich stattfinden zu lassen. Die Krümmung sollte durch Verlängerung des Halses weg von den Schultern entstehen, der Kopf wird über die ersten beiden Halswirbel gebeugt. Wenn diese Krümmung erreicht ist, kannst Du die HWS weiter leicht krümmen und dann die BWS folgen lassen.</li>
<li>Bei der idealen Kopfneigung solltest Du das Kinn nicht zur Brust drücken. In den Raum zwischen Kinn und Brust sollte noch eine kleine Faust passen.</li>
<li>Beim Strecken des Oberkörpers aus der Bauchlage solltest Du besonders darauf achten, eine einheitliche Streckung von der BWS durch die HWS zu erreichen. Hebe den Kopf nicht zu hoch, um die HWS nicht zu überstrecken bzw. zu stauchen.</li>
</ul>
<p><strong>Die Blickrichtung bestimmt die Position des Kopfes.</strong></p>
<ul>
<li>Beim <strong>Beugen</strong> des Oberkörpers aus der Rückenlage sollte die Blickrichtung dem Grad der Krümmung angemessen sein, der für eine korrekte Ausrichtung der HWS erforderlich ist.</li>
<li>Bei der <strong>Streckung</strong> der BWS sind die gleichen Prinzipien zu beachten.</li>
<li>Beim <strong>Sitzen</strong> in neutraler Position und während aller Bewegungen sollte der Blick so gerichtet sein, dass Kopf, HWS und BWS in einer Linie sind.</li>
</ul>

<p>Du siehst bei genauerer Betrachtung, dass sich die Pilates Prinzipien ergänzen, durchdringen und unterstützen. Das Zusammenspiel lässt Dich gesünder üben und erhöht Deinen Erfolg.</p>
<p>Erinnere Dich während deines Pilates Trainings immer wieder an diese Prinzipien!</p>
<p>Wir unterstützen Dich dabei,<br />Katrin &amp; Aenna</p>',
            ],
            [
                'title' => 'Ayurvedisches Fasten: Frische Energie mit Ayurveda \u2013 Heilfasten',
                'slug' => '/ayurveda-heilfasten',
                'date' => '2021-03-02',
                'image_name' => 'zutaten_blog.jpg',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2021/03/zutaten_blog.jpg',
                'body' => '<h3>Was ist fasten? Warum wir Fasten?</h3>
<p><strong>Fasten ist ein Naturphänomen. Menschen fasten seit Jahrtausenden.<br />
</strong>Fasten ist der bewusste Verzicht auf Nahrung und Genussmittel für eine begrenzte Zeit. Man gibt dem Körper die Chance, sich zu entschlacken, zu entgiften und zu reinigen. Ziel ist es, Selbstheilungsprozesse in Gang zu setzen und das körpereigene Abwehrsystem zu stärken. Fasten heißt aber nicht Hungern, der Körper soll sich erholen. Wer richtig fastet, hat eine gute Leistungsfähigkeit ohne Hungergefühl.</p>
<h3>Finde Deine optimale Fastenform.</h3>
<p>Seit vielen Jahren faste ich regelmäßig im Frühjahr und habe verschiedene Fastenarten für mich probiert. Die ersten Jahre habe ich nach nach Buchinger gefastet, später habe ich das Saftfasten entdeckt und bin dann bei einer Mischform aus beidem geblieben. Für mich hat sich eine Fastenform bewährt, die den Körper entlasten aber auch Nährstoffe liefert. Damit kann ich weiterhin meinem Training und meinem Beruf mit intensiver körperlicher Tätigkeit nachgehen.</p>
<p>Natürlich kann jeder, sofern keine Kontraindikationen vorliegen. Möchtest Du allerdings einen Muskelabbau verhindern oder während der Fastenzeit Deinem Beruf und Deinem sportlichen Training in normalen Ausmaß nachgehen, sind einige Besonderheiten zu beachten. Während eine Nulldiät unbedingt zu vermeiden ist, kann das Teilfasten sogar eine Leistungssteigerung mit sich bringen. Dabei gilt es, auf eine regelmäßige Aufnahme der „richtigen“ Nahrung zu achten. So verhindert man, dass der Körper durch Anstrengung in einen gefährlichen Nährstoffmangel kommt.</p>
<p><strong>Um für sich die optimale Fastenform zu finden, lohnt es sich genauer mit den unterschiedlichen Arten des Fastens zu befassen und auch andere Kulturen nach Rat zu fragen.</strong></p>
<h3>Was ist Ayurveda – Heilfasten?</h3>
<p><strong>Heilfastens mit Ayurveda ist typgerechtes Teilfasten.</strong><br />
Es beinhaltet ein bekömmliches Nahrungsangebot und ist eine wunderbare effektive Alternative für Menschen, die sich bei Fastenkuren keine Versorgungspause leisten können oder sich mit komplettem Nahrungsmittelverzicht schwertun und unwohl fühlen. Das Geheimnis dieser Methode ist das Wissen um die Zutaten und vorallem die Gewürze. Sie werden im Ayurveda als Therapeutikum eingesetzt.</p>
<p><strong>Ayurveda Heilfasten ist vegan und schonend. Was genau beim ayurvedischen Fasten anders ist und wo die Vorteile sind, erklärt uns Marie-Luise Auwärter-Seitz – unser angehender </strong><strong>Ayurveda-Lifestyle-Coach</strong><strong>.</strong></p>
<p>Ayurveda ist eine ganzheitliche Heilkunde und bedeutet <strong>„Wissenschaft vom langen Leben“</strong>. Es ist eine Gesundheitslehre, die sich mit den verschiedenen Einflüssen auf das menschliche Leben auseinandersetzt.</p>
<p>Ayurveda umfasst mehr als nur „Medizin“. Es werden sowohl Methoden gelehrt für einen gesunden Lebensstil, die Heilung von Krankheiten im Sinne einer ganzheitlichen Heilkunde, als auch das Erreichen eines langen Lebens. Ziel ist die Erhaltung oder Wiederherstellung der Balance zwischen Körper, Geist und Seele.</p>
<p>Dabei spielen die Ernährung und die Verdauung eine wichtige Rolle. Wenn der Körper zu wenig Verdauungsfeuer „Agni“ hat, entstehen zuviel Stoffwechselschlacken und die Nahrung kann dadurch nicht richtig verdaut werden. Es ist also nicht nur wichtig, was man isst, sondern auch, ob der Körper die Nahrung verarbeiten kann. Arbeitet das Verdauungsfeuer schwach, kann der Körper die Nahrungsmittel nicht vollständig verarbeiten. Man fühlt sich müde und schlapp, nimmt leicht zu und das Immunsystem wird geschwächt.</p>
<h3>Fasten mit Ayurveda ist ein Teilfasten</h3>
<p>Eine Fastenkur nach ayurvedischen Prinzipien reinigt den Körper von Stoffwechselschlacken, stärkt das Verdauungsfeuer „Agni“ und kurbelt den Stoffwechsel an. <strong>Außerdem hat die ayurvedische Fastenkur einen verjüngenden Effekt: Alle Körperzellen bekommen frische Energie und arbeiten wieder auf Hochtouren.</strong></p>
<p>Der Unterschied zu anderen Fastenmethoden besteht darin, dass es ein Teilfasten ist und ein bekömmliches Nahrungsangebot beinhaltet. Im Ayurveda wird der komplette Verzicht auf Nahrung meist nicht empfohlen, da das Verdauungsfeuer und die Stoffwechseltätigkeit dann ruhen und dadurch geschwächt werden. Gut veranschaulicht wird dieser Effekt durch die Vorstellung eines brennenden Kaminfeuers: Wird kein Holz nachgelegt, erlischt das Feuer. Wird ungeeignetes Holz aufgelegt, erlischt es frühzeitig und verkohltes Brennmaterial bleibt zurück.</p>
<h3>Beim Ayurveda Fasten isst man weniger, aber nicht gar nichts</h3>
<p><strong>Wichtigster Bestandteil bei dieser Methode des Fastens ist das Wissen um die Geheimnisse der Gewürze.</strong> Sie werden im Ayurveda als Therapeutikum eingesetzt. In den ayurvedisch zubereiteten Gerichten werden die Gewürze gezielt verwendet, um das Verdauungsfeuer zu entfachen und den Stoffwechsel anzuregen. Durch die Kunst der Zubereitung und des Würzen können Nahrungsmittel so zu Heilmitteln werden.</p>
<p>Unterstützend wirken während der Fastenkur ausschließlich heiße Getränke, wie Gewürztees und Ingwerwasser und wärmende Ölmassagen und Bäder.</p>

<p><strong>Zutaten für 2 Portionen<br />
</strong><strong>Zubereitungszeit ca. 30 Minuten</strong></p>

2 1/2 EL Basmati-Reis (50g)
50 g MungDal geschälte oder ganze
ca. 250 g Gemüse je nach Geschmack / in diesem Fall Spinat
1 TL Ghee oder Sesamöl
1/2 TL Kreuzkümmel
Gewürze auch je nach belieben auch noch Bockshornklee, Ingwer, Kardamon, Koriander, Kurkuma, <br />1/2 TL Steinsalz
1TL Zitronensaft
500 ml Wasser
<p><strong>So lecker kann Ayurvedisch Fasten sein!</strong></p>
<h3>Ayurvedisches Kitchari mit Gemüse – ein reinigendes &amp; entgiftendes Gericht und ein echtes Kraftpaket mit essentiellen Aminosäuren</h3>
<p>Kitchari ist ein Gericht mit heilender und entgiftender Wirkung. Man kann es eigentlich zu jeder Tageszeit essen, am besten jedoch zum Frühstück oder Mittagessen. Aufgrund seiner wohltuenden und ausleitenden Wirkung, wird es auch sehr gerne für Fastenkuren verwendet. Während einer ayurvedischen Kur, isst man Kitchari oft auch drei mal täglich. Durch die enthaltenen Gewürze wird das Verdauungsfeuer angeregt und somit der ganze Stoffwechsel angekurbelt, was den Detox-Effekt des Kitcharis ausmacht.</p>
<p>Das klassische Kitchari-Rezept besteht aus Basmati-Reis und grünen Mungbohnen, aber auch geschälte und halbierte Mungbohnen können verwendet werden. Die geschälte Mungbohne muss nicht über Nacht eingeweicht werden und ist für ein schnelles Kitchari somit bestens geeignet. Klassische Kitchari-Gewürze sind Kreuzkümmel, Koriander, Kurkuma und frischer Ingwer.</p>
<p><strong>Zubereitung</strong></p>
<ol>
<li>
Reis und MungDal in einem Sieb ca. 30 min einweichen lassen, macht es deinem Agni noch leichter, danach waschen und überschüssiges Wasser abrtropfen lassen. Gemüse waschen, putzen und klein schneiden
</li>
<li>
Einen Topf ohne Fett erhitzen und die Reis/Dal-Mischung darin 2 Minuten under Rühren trocken rösten Die Masse kurz in eine separate Schüssel geben

</li>
<li>
In dem heißen Topf das Ghee oder Öl(ohne Fett) erhitzen und die Gewürze hinzufügen. Unter rühren die Gewürzen kurz anrösten. Die Gewürze dürfen nicht anbrennen. Dann kommt das Gemüse dazu, das auch kurz anbraten. Am Schluss die Reis/Dal-Mischung und das Wasser dazu geben und alles nochmal kurz aufkochen lassen.
</li>
<li>
Die Hitze reduzieren und das ganze einkochen lassen.

</li>
<li>
Das Kitchari mit dem Salz  (salzen erst zum Schluss, sonst werden die Hülsenfrüchte nicht weich) und der Zitrone abrunden
</li>
</ol>
<p>Guten Appetit!</p>
<strong>Expertin: Marie-Luise Auwärter-Seitz </strong>
<em>Nach mehr als 15 Jahren als Yoga- und Pilatespraktizierende, absolviert Marie-Luise seit 2020 ihre Ayurveda Ausbildung bei Dr. med. Janna Scharfenberg (Ärztin, Ayurveda-Expertin, Gesundheits- &amp; Ernährungscoach, Yogalehrerin). Mit dem Ayurveda ist sie über ihr großes Interesse am Thema Ernährung in Kontakt gekommen. Luise möchte mit ihrer Arbeit dafür sorgen, dass sich gesunde Gewohnheiten harmonisch in den Alltag integrieren lassen. </em><em>Mit ihrem Wissen aus dem Ayurveda ist sie eine willkommene Bereicherung für unser Angebot und ein weiteres Tool, um dafür zu sorgen, dass ein gesunder Geist in einem gesunden Körper wohnt.</em>',
            ],
            [
                'title' => 'Pilates f\u00fcr M\u00e4nner',
                'slug' => '/pilatesmen',
                'date' => '2020-09-16',
                'image_name' => 'pilatesmen-e1600250406920.jpg',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2020/09/pilatesmen-e1600250406920.jpg',
                'body' => '<h3><span>Pilates ist kein Frauensport!<br />
</span>Darum machen Männer Pilates: Muskelaufbau, Stabilität, Flexibilität, Kontrolle, Leistungssteigerung, Prävention</h3>
<p>Viele Männer wissen leider heute nicht genug über Pilates und die Vorteile des Trainings. Dabei entstammt Pilates einer reinen Männerdomäne. Joseph Pilates entwickelte seine Methode ursprünglich als Training für Soldaten und durch die Wirksamkeit und Effizienz fand es schon damals zunehmend Anklang bei Leistungssportlern. Auch heute ist Pilates noch das bestgehütete Geheimnis vieler Profisportler, für Kraft, Kontrolle und ihren Wettbewerbsvorteil.<br />
Pilates bietet Männern, die ihre Kraft, Koordination und Beweglichkeit verbessern wollen zahlreiche Vorteile. Ob Brust-, Bein-, Rücken- oder Rumpfmuskulatur – Pilates Übungen sind eine perfekte Kombination aus Fitness- und Krafttraining für den gesamten Körper – ganz ohne zentnerschwere Hanteln.<br />
Viele Männer täten gut daran, sich einem Ganzkörpertraining mit Pilates zu unterziehen, statt ausschließlich einem Ausdauer- oder Krafttraining mit Hanteln und anderen schweren Gewichten nachzugehen. Das Resultat davon sind nämlich oft verkürzte und verletzungsanfällige Muskeln, die schnell ermüden und nicht besonders alltagstauglich sind!</p>
<h3>Hier liegen die Vorteile des Pilates Trainings:</h3>
<ul>
<li>Stärkung vernachlässigter Muskelgruppen.</li>
<li>Verbesserung der Bewegungsabläufe und des Bewegungsradius.</li>
<li>Stärkung von Bauch und Rücken.</li>
<li>Förderung der Flexibilität.</li>
<li>Linderung von Rückenschmerzen.</li>
<li>Verbesserte Körperhaltung.</li>
<li>Verbessertes Körperbewusstsein.</li>
<li>Minderung des Verletzungspotentials und Vorbeugen von Verschleiß.</li>
<li>Leistungssteigerung.</li>
<li>Fördert kraftvolle, explosive und äußerst kontrollierte Bewegungen in Deinem Sport.</li>
</ul>
<p>…selbst beim Marathon, Tennis, Golf, Ballsportarten, Klettern u.v.a. Sportarten wirkt Pilates enorm unterstützend und fördert verletzungsfrei Deine Höchstleistung.</p>
<h3>Was unterscheidet Pilates für Männer von Pilates für Frauen?</h3>
<p>Die Unterschiede finden sich bei der Auswahl der Übungen und dem Einsatz von Pilatesgeräten. Intensität und Art der Übungen sind bei reinen Männerkursen meist höher. Männer lieben den Einsatz von sogenannten Pilates – Großgeräten, wie dem Pilates Reformer, Chair oder Cadillac, um die Muskeln stärker zu beanspruchen. Das Training gegen die Federwiderstände gibt ein gutes Feedback zur eigenen Muskelarbeit und entspricht allen modernen Gesichtspunkten von „Functional Training“, das derzeit voll im Trend liegt. Denn beim Pilates wird in Muskelketten trainiert, die im Alltag und Sport gut zusammenarbeiten müssen und der ganze Körper wird bei jeder Übung ganzheitlich gefordert.</p>
<p>Immer mehr Männer entdecken inzwischen genauso wie Frauen, dass ein regelmäßiges Pilatestraining der Schlüssel ist, für einen gesunden und fitten Körper, aufrecht und stark, gleichzeitig flexibel, ohne Rückenschmerzen und Verspannungen.</p>
<h4><strong>Also los Jungs! </strong></h4>
<h4><strong>Worauf wartest Du noch?</strong></h4>',
            ],
            [
                'title' => 'Wirbels\u00e4ulenextensionen & R\u00fcckbeugen \u2013 f\u00fcr mehr Beweglichkeit, eine bessere Haltung und Gesundheit bis ins Alter',
                'slug' => '/wirbelsaeulenextensionen-rueckbeugen',
                'date' => '2020-10-12',
                'image_name' => 'rueckenextension-scaled.jpg',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2020/10/rueckenextension-scaled.jpg',
                'body' => '<p>Sie richten Deinen oberen Rücken auf, schaffen Weite im Brustkorb und dehnen Deinen Lungen- und Herzbereich auf. Ausserdem stärken sie Deine Rückenmuskulatur und die ganze Körpervorderseite wird gedehnt. Dabei wird auch Dein Verdauungssystem gestreckt und gefördert, wodurch Du Beschwerden wie Sodbrennen oder Verdauungsprobleme lindern kannst. Durch die Dehnung Deiner gesamten Atemmuskulatur wird Dein Atem vertieft und die Sauerstoffversorgung Deines Körpers verstärkt. Das wirkt energetisierend auf Körper und Geist, Du kannst Dich besser konzentrieren und hast weniger Kopfschmerzen. Du gelangst zu neuer Kraft, nicht nur körperlich, sondern auch mental.<br />
Achtsame Körperarbeit ist nie rein körperlich, sondern hat auch immer einen psychischen Aspekt. Die körperliche Aufrichtungsarbeit unterstützt hier auch Deine seelische Aufrichtung und Balance, während die Dehnungen der Vorderseite Dir Mut zur Offenheit schenken und eine offene, flexible Geisteshaltung fördern.</p>
<p>Für viele sind diese Übungen eine besondere Herausforderung oder sogar ein Problempunkt. Vielen fehlt die Flexibilität und die Kraft, durch ungesunde Haltungs- und Bewegungsmuster in Beruf und Alltag oder durch falsch trainierte Muskeln im Sport. Verspannte, verhärtete Muskeln und Blockaden schränken die Beweglichkeit ein und führen dazu, dass andere Bereiche die Flexibilität kompensieren und möglicherweise schmerzen. Die Angst vor Schmerzen führt dann meist dazu, dass die Betroffenen diese Bewegungen meiden und in einen Teufelskreis geraten.</p>
<p>Daher wollen wir Dir erklären und helfen, wie Du ohne Schmerzen, auf eine sichere und gesunde Weise, Rückenextensionen üben und möglicherweise langsam vertiefen kannst. Wir begleiten Dich und helfen Dir, Schritt für Schritt einen neuen Bewegungsraum für Deinen Rücken zu erarbeiten und zu entwickeln. Durch die Anwendung therapeutischer Sequenzen kannst Du verhärtete Muskeln, Verklebungen oder Blockaden lösen und Deinen Körper unterstützen, sich wieder in seiner ganzen Länge auszurichten. Du lernst viele Übungen zur Mobilisierung und Kräftigung, um Deinen Rücken gesund und beweglich zu halten.</p>
<h3><p>Was braucht es also und woran wollen wir arbeiten?</p></h3><ul><li><i aria-hidden="true"></i>
<p>Öffnung des Brustraumes und tiefes Atmen</p>
</li><li><i aria-hidden="true"></i>
<p>Mobilisierung in Brustwirbelsäule &amp; Schulterregion</p>
</li><li><i aria-hidden="true"></i>
<p>Länge &amp; Stabilität in der Lendenwirbelsäule</p>
</li><li><i aria-hidden="true"></i>
<p>Kräftigung der Rücken- und Bauchmuskulatur</p>
</li><li><i aria-hidden="true"></i>
<p>Entspanntheit &amp; Flexibilität im Hüftgelenk</p>
</li><li><i aria-hidden="true"></i>
<p>Elastizität und Geschmeidigkeit im frontalen Faszienzug</p>
</li></ul><p>Wir wollen Dir in unseren Pilates &amp; Yoga Stunden Möglichkeiten zeigen, wie Du durch regelmäßiges Üben, der richtigen Ausführung sowie Geduld und Respekt vor den eigenen Grenzen, ein aufrechtes, starkes und dennoch flexibles Rückgrat entwickeln kannst. Wenn Du Dich darauf einlässt, wirst Du zu einer ausgeglichenen harmonischen Beweglichkeit finden, die Dir hilft, Wirbelsäulenextensionen und Rückbeugen zu üben und ihre volle Wirksamkeit zu genießen. Du wirst Dich neu und ganzheitlicher erfahren und ein Gefühl von Freiheit und große Weite erleben.</p>
<h3><p>Eine gesunde, flexible Wirbelsäule ist die Grundlage für Dein körperliches und psychisches Wohlbefinden.</p></h3><p>Lass uns gemeinsam mit sanften Varianten beginnen, mit Verständnis und Bewusstheit kannst Du Dich dann Schritt für Schritt steigern. Nur mit einer gesunden und kontinuierlichen Praxis wirst Du die Fortschritte schnell spüren und sehen können.</p>
<p>Wir freuen uns, wenn Du mit uns übst und sind gespannt, was Du erlebst und wie sich Dein Rücken nach der Praxis anfühlt?<span><br />
</span>Erzähle uns gerne von Deinen Erfahrungen und Veränderungen!</p>',
            ],
            [
                'title' => 'Unser K\u00f6rper \u00f6ffnet den Zugang zu uns selbst',
                'slug' => '/unser-koerper-oeffnet-den-zugang-zu-uns-selbst',
                'date' => '2020-06-21',
                'image_name' => 'Juni3-1.jpg',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2020/06/Juni3-1.jpg',
                'body' => '<h3><span>Warum ist unser Körper so wichtig für Veränderung im Leben?<br />
</span></h3>
<p>In meiner Arbeit als Pilates &amp; Yoga Lehrerin steht die Körperwahrnehmung und Körperaufmerksamkeit immer im Fokus. Ich übe mit Menschen, ihren Körper wahrzunehmen und zu fühlen, gesund und auch herausfordernd zu bewegen, wie sie ihren Körper auf- und ausrichten. Ich zeige ihnen wie sich unbewusste Denkmuster und Verhaltensweisen in ihrem Körper äußern oder wie Vergangenes immer noch im Körper gespeichert ist. Und dabei werde  ich oft gefragt, warum der körperliche Ansatz so wichtig ist.</p>
<p>Ich sehe darin einen ganz besonderen Wert. Wenn wir mit unserem Körper üben, bringt er uns mit seinen Wahrnehmungen in die Erfahrung des Hier und Jetzt. Durch körperliche  Erfahrung lernen wir schneller, wir verinnerlichen das Wissen besser, als wenn wir etwas nur über unseren Kopf lernen. Eine körperliche Erfahrung verändert uns tief innen und das gewonnene Wissen wird Teil von uns.</p>
<h3>Der Körper ist der Übersetzer der Seele</h3>
<p>Über unseren Körper speichern wir aber nicht nur Positives, sondern auch alles was uns belastet, unangenehme Erfahrungen, Angst, Schmerz, Überforderung. Das prägt unser Denken und Verhalten, wir lernen uns zu schützen, wir verspannen, um nicht zu fühlen oder um uns zu betäuben. Wir entwickeln negative Glaubenssätze und Verhaltensmuster, die uns das Leben unnötig schwer machen.</p>
<p>Vielen ist klar, dass wir unser Denken und Handeln verändern müssen, wenn wir unglücklich und unzufrieden mit uns und unserem Leben sind. Positives Denken, Glaubenssätze ändern und schlechte Angewohnheiten stoppen ist dabei sehr wichtig. Worüber wir uns aber oft nicht bewusst sind, wie wichtig bei dieser Veränderung auch das Miteinbeziehen unseres Körpers ist. Denn negative innere Haltungen und Denkmuster zeigen sich in unserem Körper. Wie wir denken, fühlen, handeln – all das hat großen Einfluss auf unseren Organismus und kann sich in verschiedenen körperlichen Symptomen zeigen. Häufig äußern sie sich in Rückenschmerzen, Nackenverspannungen und Spannungskopfschmerzen, chronische Erschöpfung, Fibromyalgie, um nur ein paar zu nennen.</p>
<p>Ich bin der Ansicht, dass sich unser Geist über unseren Körper an der Oberfläche zeigt und uns sagt wie die Dinge gerade sind und wie wir uns wirklich fühlen. Bis zu einem gewissen Grad können wir unseren Körper austricksen, indem wir verdrängen, ihn stimulieren oder mit Hilfe von Medikamenten beruhigen, aber letztendlich sagt unser Körper immer die Wahrheit. Wenn wir die Realität verdrängen und nicht respektieren, wird uns eines Tages unser Körper die Rechnung präsentieren, kompromisslos und ohne eine Entschuldigung zu akzeptierten.</p>
<h3>Der Sprache des Körpers lauschen und verstehen</h3>
<p>Vielleicht kennst Du auch das Gefühl, wenn Du voller Enthusiasmus Deine Matte betrittst und schon am Beginn Deiner Praxis spürst, wie erschöpft Du eigentlich bist oder dass Bereiche Deines Körpers verspannt sind und schmerzen. Wenn wir die Gegebenheiten respektieren, uns auf unseren Körper einlassen und angemessen und aufmerksam mit ihm arbeiten, wird sich unser Blick für diese Symbolik öffnen. Wir werden die Probleme und Hindernisse, die sich an der Oberfläche zeigen erkennen, sie ins Bewusstseins rücken, untersuchen, verstehen und in unserem Inneren Lösungen finden. Wir stoßen in uns einen Entwicklungsprozess an, der uns auf einen heilsamen Weg führt. Es kann ein neuer Weg entstehen, ein Weg der Transformation, über den wir auf eine tiefere Ebene unseres Selbst gelangen.</p>
<p>Wenn wir uns also in unserer körperlichen Praxis um größtmögliche Aufmerksamkeit bemühen, Achtsamkeit im Körper (Körperbewusstsein) kultivieren, öffnet sich uns eine andere Welt. Wir lernen unterschwellige Botschaften zwischen den Zeilen zu lesen, Körpersignale und Intuition zu erfassen und in unserer Mitte zu bleiben und aus der inneren Kraft heraus zu handeln. Wir legen etwas frei, dass bereits in unserem Körper da ist, aber von dem wir abgeschnitten waren. Wenn wir den Zugang zu unserem Körper finden, Spannungen und Blockaden lösen, finden wir auch wieder Zugang zu uns Selbst und der in uns wohnenden Kraft.</p>
<p>Und das faszinierende ist, wir haben jederzeit sofortigen Zugriff auf unseren Körper, um uns wieder ins Hier und Jetzt zu bringen und in unsere innere Kraft zu kommen.</p>
<p>Bist Du bereit? Möchtest Du tiefer in die Geheimnisse Deines Körpers und in die Tiefen deiner Seele eintauchen?<br />
Dann schau doch mal bei uns vorbei. Entdecke Dich selbst, sei Dein eigener Lehrer und bestimme die Mächte in Deinem Körper und Deinem Leben selbst.</p>
<p>Namste Katrin</p>',
            ],
            [
                'title' => 'Bist du bereit f\u00fcr den n\u00e4chsten Schritt?',
                'slug' => '/bist-du-bereit-fuer-den-naechsten-schritt',
                'date' => '2020-04-11',
                'image_name' => 'IMG_1089-scaled-e1589357664658.jpg',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2020/05/IMG_1089-scaled-e1589357664658.jpg',
                'body' => '<p>Kennst du das, du willst etwas anpacken, lernen oder in deinem Leben verändern, aber du stehst an einem Punkt, an dem du nicht weiterkommst?</p>
<p>Ich kenne das super gut. Immer wieder werde ich gefordert etwas in meinem Leben zu verändern. Manchmal geht alles ganz einfach und ein anderes Mal arbeitet eine innere Bremse gegen mich, stoppt mich oder macht mich ungeduldig.</p>
<p>Das Erlernen von Pincha Mayurasana hat mich mit meinen inneren Mustern konfrontiert und auseinander setzen lassen, die mich vielleicht auch im Leben vom nächsten Schritt abhalten.</p>
<p>Die Angst, den nächsten Schritt zu riskieren, in dem Fall die Beine endlich frei im Raum zu heben. Der Mangel an Geduld, der sich einstellt, wenn es nicht vorwärts geht. Aus Ungeduld wird dann oft Unachtsamkeit uns selbst gegenüber, wir gehen über unsere Grenzen hinaus. Das führt dazu, dass wir uns schaden, weil wir uns nicht genug Zeit geben.</p>
<p>Pincha Mayurasana hat mich sehr bewusst erfahren lassen, wie wir Schritt für Schritt erstaunlich weit vorankommen, wenn wir mit Beständigkeit (Abhyasa) üben, ohne uns dabei Druck zu machen, also mit Gelassenheit (Vairagya).</p>
<p>Beständiges Üben braucht Selbstdisziplin (Tapah), das ist Begeisterung an der einen Sache, die uns den Antrieb gibt, es immer wieder neu zu versuchen und der Wille sich weiterzuentwickeln. Und wo ein Wille ist, da ist auch ein Weg. Wenn wir mit einem hohen Maß an Selbstachtsamkeit und Selbstreflexion (Svadhyaya) üben, werden wir im Inneren einen passenden Weg für uns finden. Es ist ein Weg mit Rücksicht auf die eigenen Gegebenheiten, mit Akzeptanz unserer Grenzen und dem lösen von jeglichen Erwartungen (Isvara Pranidhana).</p>
<p>Durch diese Erkenntnis werden wir innerlich freier und können gelassen, vertrauensvoll und offen gegenüber allem Unvorhersehbaren den nächsten Schritt machen.</p>
<p>Auch im Alltag gibt es viele kleine Schritte, um sich in diesen Tugenden zu üben.</p>
<p>Probiere es aus!</p>',
            ],
            [
                'title' => 'Yoga Sutra 1, Satz 33 // maitr\u012b karu\u1e47\u0101 mudito-pek\u1e63\u0101\u1e47\u0101\u1e41...',
                'slug' => '/maitri-karuna-mudito-peksanam-sukha-duhkha-punya-apunya-visayanam-bhavanatah-citta-prasadanam-yoga-sutra-1-s',
                'date' => '2020-03-24',
                'image_name' => 'SonneimHerz-scaled-e1589357562776.jpg',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2020/03/SonneimHerz-scaled-e1589357562776.jpg',
                'body' => '<p>Das wandelbare Wesen des Menschen wird harmonisiert durch die Kultivierung von Empathie, Hilfsbereitschaft, positive Bestätigung und Fehlerfreundlichkeit in Situationen von Glück, Leid, Erfolg oder Misserfolg.</p>
<p>Warst du bei unserer Online-Meditation dabei? Es hat mich sehr berührt, ⁣meine Erfahrung mit so vielen Menschen teilen zu dürfen.</p>
<p>Eine regelmäßige Praxis kann dich unterstützen achtsamer und gelassener mit dir selbst und deinen Mitmenschen umzugehen. Egal was dich gerade herausfordert, Meditation kann dir helfen mehr in deiner Mitte zu bleiben und deinen Geist nachhaltig zu stabilisieren.⠀</p>
<p>maitrī karuṇā mudito-pekṣāṇāṁ-sukha-duḥkha puṇya-apuṇya-viṣayāṇāṁ bhāvanātaḥ citta-prasādanam</p>
<p>Diesen Satz 1.33 aus dem Yoga Sutra übersetzt mein Lehrer, Dr. Ronald Steiner wie folgt:<br />
Das wandelbare Wesen des Menschen wird harmonisiert durch die Kultivierung von Empathie, Hilfsbereitschaft, positive Bestätigung und Fehlerfreundlichkeit in Situationen von Glück, Leid, Erfolg oder Misserfolg.</p>
<p>Ein Satz, den ich nicht nur in der aktuellen Situation für besonders wichtig halte. Hier stellt uns Patañjali einen Weg zur Klärung unseres Geistes vor und wie wir die Hindernisse auf dem Weg dorthin überwinden können. Es ist die Entwicklung der vier Tugenden (Herzensqualitäten), gegenüber anderen, gegenüber dir selbst und gegenüber allen Erfahrungen: ⠀</p>
<p>maitrī = Liebe, Freundlichkeit, Güte, Empathie⠀<br />
karuṇā = Hilfsbereitschaft, Mitgefühl, Wohlwollen,⠀<br />
mudito = Fröhlichkeit, Begeisterung, positive Bestätigung⠀<br />
upekṣana = Gelassenheit, Gleichmut, Fehlerfreundlichkeit, Nachsicht⠀</p>
<p>Die Kultivierung von Maitrī, Karuṇā, Mudita und Upekṣa verändert unsere Wahrnehmung und zieht wellenartige Kreise nach außen. Wenn du den Zugang zur Liebe in dir selbst findest, wachsen die anderen Tugenden wie von selbst und du bist voller Kraft und hast viel zu geben.⠀</p>
<p>Ich selbst versuche mich so oft wie möglich an diese Tugenden zu erinnern, in meinem privaten Leben und als Yoga- und Pilateslehrer.⠀</p>
<p>Ich möchte euch in meinem Yoga Unterricht gerne weitere Meditationstechniken vorstellen<br />
und dabei mit euch tiefer in die Yogaphilosophie eintauchen.</p>
<p>Ich freue mich auf dich, Katrin.</p>
<p>Namaste</p>',
            ],
            [
                'title' => 'Armkraft, Stabilit\u00e4t und Leichtigkeit f\u00fcr den Handstand',
                'slug' => '/armkraft-stabilitaet-und-leichtigkeit-fuer-den-handstand',
                'date' => '2018-09-10',
                'image_name' => '16_Handstand-e1589357858661.jpg',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/16_Handstand-e1589357858661.jpg',
                'body' => '<h3>Knee raises and<br />
Knee raises with obliques on the Pilates Chair</h3>
<p>Eine herausfordernde Übung für den gesamten Körper. Du entwickelst Kraft und Stabilität in Rumpf, Schultern und Armen, um das Gewicht deines Körpers zu tragen.<br />
Gleichzeitig trainierst Kraft und Beweglichkeit in Hüfte und Knien. Du schaffst eine gut Basis, um dich später auch kopfüber gut zu stabilisieren.</p>

<h3>Handstand am Pilates Stability Chair.</h3>
<p>Diese fortgeschrittene Chair Übung ist sehr schwierig und es braucht Übung, um die Pedale zu liften und wieder nach unten zu drücken. Kontrolliertes Üben und das richtige Maß an Kraft und Leichtigkeit und ist hier absolut entscheidend.<br />
Deine Stützmuskulatur und deine Körpermitte werden hier mehr gefordert als im freien Raum. Dadurch entwickelst du Kraft, Stabilität und Sicherheit sowie Verständnis und Körperbewusstsein für den Handstand im freien Raum.</p>

<video id="video-13144-3" width="720" height="1280" preload="metadata" controls="controls"><source type="video/mp4" src="https://www.centerofpilatesyoga.de/wp-content/uploads/2019/06/38784544_273300643455290_4353239110169657344_n.mp4?_=3" /><a href="https://www.centerofpilatesyoga.de/wp-content/uploads/2019/06/38784544_273300643455290_4353239110169657344_n.mp4">https://www.centerofpilatesyoga.de/wp-content/uploads/2019/06/38784544_273300643455290_4353239110169657344_n.mp4</a></video>

<video id="video-13144-4" width="720" height="1280" preload="metadata" controls="controls"><source type="video/mp4" src="https://www.centerofpilatesyoga.de/wp-content/uploads/2019/06/38745062_229922917855563_3283967513901137920_n.mp4?_=4" /><a href="https://www.centerofpilatesyoga.de/wp-content/uploads/2019/06/38745062_229922917855563_3283967513901137920_n.mp4">https://www.centerofpilatesyoga.de/wp-content/uploads/2019/06/38745062_229922917855563_3283967513901137920_n.mp4</a></video>',
            ],
            [
                'title' => 'Warum ist Pilates so gut f\u00fcr Dich?',
                'slug' => '/warum-ist-pilates-so-gut-fuer-dich',
                'date' => '2016-11-30',
                'image_name' => 'IMG_9190-scaled-e1589357799944.jpg',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2020/05/IMG_9190-scaled-e1589357799944.jpg',
                'body' => '<p>Rund 100 Jahre alt, aber aktueller denn je. Ein starker Rücken, ein fester Bauch und ein knackiger Po, das versprechen Pilatesübungen. Pilates macht aber nicht nur schlank und schön, es steigert das Wohlbefinden und bringt auch den Geist wieder ins Gleichgewicht.</p>
<p>„In der Ruhe liegt die Kraft“ – das gilt  bei Pilates und ist der entscheidende Vorteil. Pilates, das sind ruhige und kraftvoll ausgeführte Bewegungen, ohne Schwung und ohne laut hämmernde Beats, üben mit voller Aufmerksamkeit und größter Genauigkeit. Hocheffektive Bewegungen kräftigen die Tiefenmuskulatur, wobei der Atem die Bewegung führt. Ein ganzheitliches Körpertraining mit dem Fokus auf die Körpermitte das dem Rumpf Kraft und Stabilität verleiht. Dabei wird das Bewusstsein für den eigenen Körper intensiviert, der Bewegungsfluss, die Koordination und Beweglichkeit verbessert.</p>
<p>Bleib dran und alles wird sich fügen: ein straffer, starker Körper und ein ansteckendes Selbstbewusstsein.</p>
<p>95126 Schwarzenbach a. d. Saale<br>Schwingener Weg 1</p>
<p>Telefon: <a href="tel:+49 9284 8017822">+49 9284 8017822</a></p>
<p>E-Mail: <a href="mailto:&#105;n&#102;o&#64;center&#111;&#102;&#112;&#105;&#108;a&#116;es&#121;&#111;&#103;a.de">info@centerofpilatesyoga.de</a></p>
<p>Webseite: <a href="http://www.info@centerofpilatesyoga.de">www.info@centerofpilatesyoga.de</a></p>',
            ],
            [
                'title' => 'Gesunder Nacken und Schultern',
                'slug' => '/gesunder-nacken-und-schultern',
                'date' => '2018-07-27',
                'image_name' => '0_NeckShoulders-e1589357883488.jpg',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/0_NeckShoulders-e1589357883488.jpg',
                'body' => '<h3>&#8230;..darum dreht es sich bei uns im Monat Juli, Verspannungen lösen, Muskeln stärken.</h3>
<p>Viele von uns schlagen sich regelmäßig mit Verspannungen in Nacken und Schultern herum. Nackenverspannungen können auf vielfältige Arten entstehen. Häufig gehen sie mit Spannungskopfschmerzen oder ausstrahlenden Schmerzen in den Armen einher. Das geht bis hin zu Schlafstörungen, Müdigkeit und Konzentrationsverlust.</p>
<p>Die häufigste Ursache für Nackenverspannungen ist eine schlechte Körperhaltung während wir stundenlang am Computer sitzen oder auf das Smartphone starren. Aber auch ungünstige Schlafpositionen, mangelnde Beweglichkeit, zu schwache Muskeln im Bereich der Rücken- und Nackenmuskulatur, verklebte Faszien, und vor allem auch Stress können zu Nackenverspannungen führen.</p>
<p><em>Pilates kann dir dabei helfen, die Verspannungen in Nacken und Schultern zu lösen, gleichzeitig die Muskulatur in dem Bereich zu kräftigen und dabei deine Körperhaltung zu verbessern. So dass es bei einer regelmäßigen Praxis in Zukunft nicht mehr zu Verspannungen kommen kann.</em></p>

		<dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="Nackendehnung" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/1_Nackendehnung.jpg\'></a>
			</dt></dl><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="Faszienmassage" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/2_Faszienmassage.jpg\'></a>
			</dt></dl><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="Push-ThruOnStomach" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/3_Push-ThruOnStomach.jpg\'></a>
			</dt></dl><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="Push-ThruOnStomachExtended" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/4_Push-ThruOnStomachExtended.jpg\'></a>
			</dt></dl><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="ArmsBackward" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/5_ArmsBackward.jpg\'></a>
			</dt></dl><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="HighRow" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/6_HighRow.jpg\'></a>
			</dt></dl><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="Reformer" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/7_RotationSpine.jpg\'></a>
			</dt></dl><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="SideTwistPunch" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/8_SideTwistPunch.jpg\'></a>
			</dt></dl><br /><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="SideBendModification" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/9_SideBendModification.jpg\'></a>
			</dt></dl><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="Backhand" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/10_Backhand.jpg\'></a>
			</dt></dl><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="SalutBackExtended" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/11_SalutBackExtended.jpg\'></a>
			</dt></dl><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="Swimmer" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/13_Swimmer.jpg\'></a>
			</dt></dl><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon portrait\'>
				<a data-rel="iLightbox[postimages]" data-title="TricepsPressStanding" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/14_TricepsPressStanding.jpg\'></a>
			</dt></dl><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="OneArmPushHandonFloor" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/15_OneArmPushHandonFloor.jpg\'></a>
			</dt></dl><dl class=\'gallery-item\'>
			<dt class=\'gallery-icon landscape\'>
				<a data-rel="iLightbox[postimages]" data-title="Handstand" data-caption="" href=\'https://www.centerofpilatesyoga.de/wp-content/uploads/2018/07/16_Handstand-e1589357858661.jpg\'></a>
			</dt></dl>
			<br style=\'clear: both\' />

<p><strong>Sanfte Dehnung der Hals- und Nackenmuskeln<br />
</strong>Das fördert die Durchblutung, dehnt die verspannten Muskeln und lindert die Verspannungen.</p>
<p><strong>Faszien-Release mit Bällen und Rollen<br />
</strong>Über diese Art Massage können wir Spannung aus dem System nehmen. Es werden Verspannungen und Knotenpunkte  zwischen den Schulterblättern gelöst und der obere Anteil des Nackenmuskels und der Brustmuskel wird entspannt .</p>
<p><strong>Lockerung und Beweglichkeit verbessern<br />
</strong>Um die muskulären Dysbalancen im Nackenbereich auszugleichen, trainieren wir die Beweglichkeit in der Hals- und Brustwirbelsäule sowie im Schulterblatt-Bereich.</p>
<p><strong>Kräftigungsübungen<br />
</strong>Das wichtigste ist ein Präziser Muskelaufbau in Nacken- und Schultern. Die Aktivierung des unteren Anteils des Trapezmuskels steht hier im Vordergrund, da dieser meist zu schwach ausgeprägt ist, was zu Nackenverspannungen oder einer vorgestreckten Kopfhaltung führen kann. Ergänzend dazu wollen wir die Halsbeuger , den großen Rückenmuskel und der schulterblattumgebenden und stabilisierenden Muskeln kräftigen.</p>
<p><em>Wer kräftige Schultern und einen starken Nacken hat, hat selten Rücken- und Nackenschmerzen sowie Probleme mit der Körperhaltung.</em> </p>
<p><strong>Yoga &amp; Meditation<br />
</strong>Mit Yoga &amp; Mediation kannst du dein Pilates Training sinnvoll ergänzen, denn auch Yoga-Übungen für Schultern und Nacken wirken sehr vielseitig, sind effektiv und haben große Bedeutung für ein gesundes und entspanntes Leben. Neben der Kräftigung der Muskulatur, kann es dir zu mehr Gelassenheit und Achtsamkeit verhelfen und damit viele der Ursachen von Verspannungen im Schulter- und Nackenbereich minimieren. Yoga entspannt Körper und Geist, hilft dir beim Loslassen von Ballast und Stress, der dir im Nacken sitzt und lockert die Muskulatur.</p>
<p>Wenn du noch nicht bei uns warst, dann wird es jetzt höchste Zeit!<br />
Wir freuen uns aud Dich.</p>',
            ],
            [
                'title' => 'Zertifizierung zur AYI Inspired Yogalehrerin',
                'slug' => '/13399-2',
                'date' => '2019-07-15',
                'image_name' => 'cp_logo_retina.png',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2020/03/cp_logo_retina.png',
                'body' => '<p>Seit kurzem darf ich mich AYI Inspired Yogalehrerin nennen. Erfüllt und voller Dankbarkeit schaue ich auf das gebündelte Wissen und die Erfahrung.</p>
<p>Ich hatte bereits eine Yogalehrerausbildung und blicke auf über 4 Jahre Unterrichtserfahrung zurück. Die Bedürfnisse meiner Schüler und der Anspruch an meinen Unterricht haben mich weiter auf die Suche gehen lassen: nach mehr Struktur in der Praxis, nach präziser Technik, Modifikationen, therapeutischen Aspekten, mehr Tiefe in der Philosophie.</p>
<p>So wurde ich auf Dr. Ronald Steiner und die AYI aufmerksam. Hier vereinten sich alle Säulen meines Anspruchs. Ich besuchte einen ersten therapeutischen Kurs bei ihm und war fasziniert von seinem unglaublichen Fachwissen und wie er alt und neu verbindet. Er verknüpft das traditionelle Übungssystem des Ashtanga Yoga mit den neuesten wissenschaftlichen Erkenntnissen aus Medizin, Bewegungslehre und Psychologie. Mir war klar, hier geht mein Weg weiter!</p>
<p>Folglich habe ich mich für den Quereinstieg bei Dr. Ronald Steiner entschieden und die Ausbildung zur AYI Inspired Yogalehrerin absolviert. Die Ausbildung und die therapeutischen Kurse haben meinem Unterricht und meiner Selbstpraxis einen Quantensprung versetzt.<br />
Die feste Abfolge des Ashtanga Yoga, die Modifikationen und das Alignment der AYI Methode sind dabei ein großes Geschenk. Innovative Modifikationen ermöglichen es jedem Yoga zu üben und eine persönliche Praxis zu finden. Mit dem Alignment lernen wir uns harmonisch auszurichten, tauchen tiefer in die Selbstbeobachtung ein, konzentrieren uns mehr auf den Atem. Das schult einen achtsamen Umgang mit uns selbst, mit unserem Körper, mit dem Atem. Es entsteht eine Praxis mit hoher Achtsamkeit und Präzision und wird zu einer wirklich bewegten Meditation.</p>
<p>Es ist mir ein Anliegen, meine Freude am Ashtanga Yoga mit euch zu teilen und euch mit meiner eigenen Erfahrung als Praktizierende und dem Wissen als AYI Lehrer auf dem Yogaweg zu begleiten und individuell zu fördern.</p>
<p>Danke an alle Schüler, die mit mir im Rahmen der Ausbildung geübt haben,<br />
ganz besonders an Steffi, Regina, Petra, mit denen ich die Basic LED Class als Prüfung üben und aufnehmen durfte.</p>
<p>Namste Katrin.</p>',
            ],
            [
                'title' => 'Yoga Sutra 1, Satz 2 // yoga\u015b-citta-v\u1e5btti-nirodha\u1e25',
                'slug' => '/13383-2',
                'date' => '2020-03-08',
                'image_name' => 'devanagari-scaled-e1589357597367.jpg',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2020/03/devanagari-scaled-e1589357597367.jpg',
                'body' => '<h3>Im Zustand des Yoga sind alle Trübungen (Vritti), die im Wandelbaren des Menschen (Chitta) bestehen können, aufgelöst.</h3>
<p>Darum ging es diese Woche in meinen Fokus Stunden. Im Yoga Sutra 1, Satz 2 definiert Patanjali das Ziel des Yoga. Yoga ist das Zur-Ruhe-Bringen des Geistes und dessen Geistesbewegungen.</p>
<p>In unserer Praxis haben wir die unterschiedlichen Aspekte des Ashtanga Yoga, Atemübungen (Pranayama), Körperübungen (Asana) und Meditation verbunden, um systematisch die Bewegungen in unserem Geist zur Ruhe zu bringen. Wenn wir achtsam üben und dabei genau in uns hineinspüren, können wir einen Zustand voller Bewusstheit und Klarheit im Geist erreichen, der uns einen freien Blick auf uns Selbst und die Welt gewährt.</p>
<p>Um den Zustand des Geistes zu veranschaulichen, findet man häufig den Vergleich mit einem See. Wir sehen den Grund eines Sees erst dann, wenn sich die Wellen legen und das Wasser klar ist. Und ähnlich verhält es sich mit unserem Geist. Unser Geist (Citta) ist unser Wahrnehmungsraum oder unser innerer See. Äußere Reize (z.B. Stress, Ärger, Kritik, Lob) und innere (Krankheit, Erinnerungen, Ängste, Wut) wühlen unseren inneren See auf und die Gedankenwellen (Vrttis) verstellen die Sicht auf uns selbst. Sie beeinflussen wie wir uns in der Welt wahrnehmen.<br />
Unsere Geistesbewegung können uns klein machen oder fälschlicherweise groß. Sie lassen uns an Dingen festhalten, die ihre Zeit hatten oder nie real waren. Sie lassen uns glauben alles muss fertig werden oder hindern uns daran Dingen zu tun.</p>
<p>Wenn wir im Yoga lernen, uns beständig und achtsam auszurichten auf das was ist, ohne dabei abzuschweifen, verblassen unsere Gedankenwellen. Unser Wahrnehmungsraum wird ruhig und klar und wir können tief in uns hineinblicken und Erkenntnis gewinnen, wer wir wirklich sind.</p>
<p>Jeder kann diesen klaren Zustand der Wahrnehmung, den Patañjali als Yoga beschreibt, erreichen.<br />
Lasst uns im Ashtanga Yoga gemeinsam ein paar Schritte gehen, um unser wahres Selbst (Svatma), zu erfahren.<br />
Eine Erfahrung, die von Grund auf das Leben verändert.</p>',
            ],
            [
                'title' => 'Ashtanga Yoga Pranayama',
                'slug' => '/13360-2',
                'date' => '2020-03-15',
                'image_name' => 'IMG_1104-e1589357619528.jpg',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2020/03/IMG_1104-e1589357619528.jpg',
                'body' => '<h3>Einatmen, Ausatmen!</h3>
<p>⁣Die Sonne lockte mich heute zu einer morgendlichen Pranayama Praxis nach draußen. Atemübungen, Atemtechniken und Meditation helfen mir gerade jetzt, den durch die aktuelle Nachrichtenflut, überladenen Geist, die damit verbundenen negativen Gedanken und die innere Unruhe zu lindern.⠀</p>
<p>Eine bewusstes und tiefes atmen beruhigt nachhaltig den Atem, zentriert und klärt den Geist und wirkt darüber spürbar harmonisierend auf unsere Psyche.⠀<br />
Aber auch unser Körper profitiert sehr, und das auf allen Ebenen. Die tiefen Atemübungen des Yoga verbessern den Atemfluss, das öffnet die Atemwege, wodurch unsere Lunge vitalisiert und gereinigt wird. Das sichert nicht nur die Versorgung jeder einzelnen Zelle mit reichlich Sauerstoff, sondern beschleunigt auch Reinigungsprozesse im Körper.⠀<br />
⠀<br />
Pranayama, das achtsame Atmen und bestimmte Atemtechniken sind neben Asana (Körperübung) und Meditation ein wichtiger Bestandteil unserer Yogapraxis. Denn unser Atem hat einen maßgeblichen Einfluss auf unsere körperliche und mentale Gesundheit. Körper, Atem und Geist sind untrennbar miteinander verbunden und stehen in Wechselwirkung zueinander. Das können wir spüren, wenn psychische Belastung die Atmung und unser körperliches Wohlbefinden beeinträchtigen oder wenn der Atem ein Gefühl der Einheit zwischen Körper und Geist und ein Gefühl des inneren Friedens herstellt.⠀<br />
⠀</p>
<p><em>„Die Praxis des Yoga ist seit jeher untrennbar mit der Entfaltung der Atemachtsamkeit verbunden. Man kann sogar so weit gehen zu sagen, dass Yoga ohne Atemachtsamkeit kein Yoga ist”, so Anna Trökes und Dr. Ronald Steiner(„Yoga für Fortgeschrittene”).</em>⠀<br />
⠀<br />
⠀<br />
Willst du mehr über Pranayama erfahren?<br />
In meinem Unterricht erzähle ich dir, wie Pranayama wirkt<br />
und stelle dir Übungen vor, die dir im Alltag helfen, Stress oder negative Gedanken hinter dir zu lassen.⠀</p>',
            ],
            [
                'title' => 'Akzeptieren was ist, gehen lassen was war und Vertrauen haben in das, was kommt',
                'slug' => '/akzeptieren-was-ist-gehen-lassen-was-war-und-vertrauen-haben-in-das-was-kommt',
                'date' => '2020-05-01',
                'image_name' => 'IMG_1087-scaled-e1589357643366.jpg',
                'image_url' => 'https://www.centerofpilatesyoga.de/wp-content/uploads/2020/05/IMG_1087-scaled-e1589357643366.jpg',
                'body' => '<p>Eine innere Haltung, die uns helfen kann, um die momentane Krise gut zu bewältigen und das Beste daraus zu machen.</p>
<p>Oft begegnen uns im Leben Situationen, die uns unerwartet aus der Bahn werfen, manchmal sind es Kleinigkeiten und manchmal ist es Schwerwiegenderes. Dinge passieren, ob sie uns gefallen oder nicht und wir können vieles nicht beeinflussen. Was wir aber beeinflussen können, wie wir diesen Dingen begegnen. Wir können daran zerbrechen oder akzeptieren was ist und daran wachsen. Eine eigene leidvolle Erfahrung schenkte mir hier ein tieferes Verständnis und brachte mich so auf einen heilsamen Weg.</p>
<p>Vor einem halben Jahr manövrierte mich das Leben völlig unvorbereitet in ein körperliches Dilemma. Ich wünschte zunächst nur, es möge anders sein. Was vor kurzem noch gut war, war nicht mehr. Schmerz, Angst und Ungewissheit kamen auf, berechtigte Gefühle, die wir nicht verdrängen sollten. Wenn wir aber dauerhaft darin versinken und uns zu lange gegen die Realität wehren, raubt uns das jegliche Energie und macht uns unfähig zu handeln. Statt unsere Lage zu verbessern, verstärken und verlängern wir unser Leid.</p>
<p>Eine ganze Weile steckte ich in der Krise fest, bis ich innerlich so erschöpft war, dass ich nicht mehr konnte, nicht mehr wollte. Ich hatte von all dem Kampf genug und sehnte mich einfach nur nach innerem Frieden. Eine Instanz in mir beschloss endlich loszulassen, die Situation so anzunehmen wie sie ist und meinen Blick für das zu öffnen was hier gesehen werden wollte.</p>
<p>In einem Moment der Stille und Klarheit erkannte ich, dass in all den Schwierigkeiten auch Möglichkeiten lagen. Ich begann positiv über meine Situation zu denken und Gutes darin zu sehen. Ein Zeichen, dass ich Frieden geschlossen hatte. Indem ich mich bewusst auf die positiven Aspekte konzentrierte, schaffte ich es wieder nach vorne zu schauen. Ich nahm meine Lage ernst, ließ mich aber nicht mehr von negativen Gedanken und Gefühlen mitreißen. Das setzte neue Energie in mir frei, die ich fortan in Lösungen umsetzte, die meine Lage verbesserten. Ich spürte, ich war auf dem richtigen Weg, einem heilsamen Weg, der mich von alten Mustern befreite und mir neue Erkenntnisse und ungeahnte Möglichkeiten eröffnete.</p>
<p>Das Leben begann wieder zu fließen und es fühlte sich trotz der Situation wieder gut und positiv an – wenn ich nicht festhielt, sondern akzeptierte, was ich nicht ändern konnte und mit Vertrauen und Erwartungslosigkeit voranschritt.</p>
<p>Für diese Erfahrung bin ich heute sehr dankbar, denn sie begleitet mich gerade wie eine unsichtbare Stütze durch die nächste Krise und macht das Leben leichter.</p>
<p>Ich wünsche dir und auch mir selbst, dass es uns mit Akzeptanz und Vertrauen immer wieder gelingt,<br />
die kleinen und großen Krisen des Lebens zu bewältigen<br />
und es uns zu einem tieferen und glücklicheren Leben führt!</p>',
            ]
        ];

        foreach ($tenants as $tenant) {
            $tenantId = $tenant->id;

            $author = DB::table('authors')
                ->where('tenant_id', $tenantId)
                ->where('name', 'Katrin Auw\u00e4rter')
                ->first();

            if (! $author) {
                $authorId = DB::table('authors')->insertGetId([
                    'tenant_id' => $tenantId,
                    'name' => 'Katrin Auw\u00e4rter',
                    'bio' => 'Inhaberin CPY Center of Pilates & Yoga',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $authorId = $author->id;
            }

            foreach ($articles as $articleData) {
                $exists = DB::table('articles')
                    ->where('tenant_id', $tenantId)
                    ->whereJsonContains('slug->de', $articleData['slug'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $mediaId = null;

                try {
                    $response = Http::timeout(30)->get($articleData['image_url']);

                    if ($response->successful()) {
                        $imageContent = $response->body();
                        $extension = pathinfo($articleData['image_name'], PATHINFO_EXTENSION);
                        $baseName = pathinfo($articleData['image_name'], PATHINFO_FILENAME);
                        $storagePath = $tenantId . '/' . $articleData['image_name'];

                        Storage::disk('media')->put($storagePath, $imageContent);

                        $mediaId = DB::table('medias')->insertGetId([
                            'tenant_id' => $tenantId,
                            'type' => 'image',
                            'name' => $baseName,
                            'extension' => $extension,
                            'path' => $storagePath,
                            'disk' => 'media',
                            'size' => strlen($imageContent),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Continue without image if download fails
                }

                DB::table('articles')->insert([
                    'tenant_id' => $tenantId,
                    'author_id' => $authorId,
                    'title' => json_encode(['de' => $articleData['title']]),
                    'slug' => json_encode(['de' => $articleData['slug']]),
                    'body' => $articleData['body'],
                    'featured_image' => $mediaId,
                    'publication_date' => $articleData['date'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Don't delete seeded data on rollback
    }
};
