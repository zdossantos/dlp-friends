<?php

namespace Database\Seeders;

use App\Models\ConversationStarter;
use Illuminate\Database\Seeder;

final class ConversationStarterSeeder extends Seeder
{
    public function run(): void
    {
        $starters = [
            ['C’est quoi ton attraction préférée à Disneyland Paris ?', 'What is your favorite attraction at Disneyland Paris?'],
            ['Ton snack préféré dans les parcs, c’est lequel ?', 'What is your favorite snack in the parks?'],
            ['Avec quel personnage rêves-tu de prendre une photo ?', 'Which character would you love to take a photo with?'],
            ['Dans quel land préfères-tu passer du temps ?', 'Which land do you most enjoy spending time in?'],
            ['Quel spectacle ou quelle parade aimerais-tu revoir ?', 'Which show or parade would you love to see again?'],
            ['Tu préfères visiter les parcs à Halloween, à Noël ou pendant une autre saison ?', 'Do you prefer visiting during Halloween, Christmas, or another season?'],
            ['Quel souvenir aimes-tu rapporter après une visite ?', 'What kind of souvenir do you like bringing home after a visit?'],
            ['Il y a une musique des parcs que tu pourrais écouter en boucle ?', 'Is there any music from the parks that you could listen to on repeat?'],
            ['C’est quoi ton meilleur souvenir à Disneyland Paris ?', 'What is your favorite memory from Disneyland Paris?'],
            ['Tu as un détail de décor préféré dans les parcs ?', 'Do you have a favorite decorative detail in the parks?'],
            ['Quel conseil donnerais-tu à quelqu’un qui vient pour la première fois ?', 'What advice would you give to someone visiting for the first time?'],
            ['Pour toi, quelle attraction ne reçoit pas assez d’attention ?', 'Which attraction do you think deserves more attention?'],
            ['Tu fais quoi en premier quand tu arrives dans les parcs ?', 'What is the first thing you do when you arrive at the parks?'],
            ['Tu préfères être là dès l’ouverture ou rester jusqu’à la fermeture ?', 'Do you prefer arriving at opening time or staying until closing?'],
            ['Tu prépares toute ta journée ou tu décides sur place ?', 'Do you plan your whole day or decide what to do once you get there?'],
            ['Quand il pleut dans les parcs, c’est quoi ton programme idéal ?', 'When it rains in the parks, what does your ideal plan look like?'],
            ['Si tu pouvais organiser la journée parfaite, elle ressemblerait à quoi ?', 'If you could plan the perfect day, what would it look like?'],
            ['Il y a une expérience que tu aimerais absolument essayer lors de ta prochaine visite ?', 'Is there an experience you would love to try on your next visit?'],
        ];

        foreach ($starters as $index => [$textFr, $textEn]) {
            ConversationStarter::query()->updateOrCreate(
                ['sort_order' => $index + 1],
                ['text_fr' => $textFr, 'text_en' => $textEn, 'is_active' => true],
            );
        }
    }
}
