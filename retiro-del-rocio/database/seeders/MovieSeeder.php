<?php

namespace Database\Seeders;

use App\Models\Movie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MovieSeeder extends Seeder
{
    public function run(): void
    {
        $times = ['10:30 AM', '1:00 PM', '4:00 PM', '7:00 PM', '10:00 PM'];

        $movies = [
            ['title' => 'Avatar (The Way of Water)', 'poster' => 'images/image 222.jpg', 'backdrop' => 'images/image 5.jpg', 'genre' => 'Action, Adventure', 'duration' => '3h 12m', 'rating' => 'PG-13', 'classification' => 'now_showing', 'is_featured' => true,
                'synopsis' => 'Jake Sully and Neytiri have formed a family and are doing everything to stay together — but must leave their home and explore the regions of Pandora when an ancient threat resurfaces.'],
            ['title' => 'Dune: Part Two', 'poster' => 'images/image 5.jpg', 'backdrop' => 'images/image 5.jpg', 'genre' => 'Sci-Fi, Drama', 'duration' => '2h 46m', 'rating' => 'PG-13', 'classification' => 'now_showing', 'is_featured' => true,
                'synopsis' => 'Paul Atreides unites with the Fremen to wage war against House Harkonnen and avenge his family, torn between love and the fate of the universe.'],
            ['title' => 'Kingdom of the Planet of the Apes', 'poster' => 'images/image 222.jpg', 'backdrop' => 'images/image 222.jpg', 'genre' => 'Action, Drama', 'duration' => '2h 25m', 'rating' => 'PG-13', 'classification' => 'now_showing', 'is_featured' => false,
                'synopsis' => 'Many years after the reign of Caesar, a young ape questions everything he has been taught about the past and makes a choice that will define a generation.'],
            ['title' => 'Sinners', 'poster' => 'images/Revoir-Paris-d 2.jpg', 'backdrop' => 'images/Revoir-Paris-d 2.jpg', 'genre' => 'Thriller, Horror', 'duration' => '2h 17m', 'rating' => 'R', 'classification' => 'now_showing', 'is_featured' => true,
                'synopsis' => 'Twin brothers return to their hometown to start again, only to discover that a far greater evil is waiting to welcome them back.'],
            ['title' => 'The Herd', 'poster' => 'images/image 361.jpg', 'backdrop' => 'images/image 361.jpg', 'genre' => 'Action, Drama', 'duration' => '2h 30m', 'rating' => 'PG-13', 'classification' => 'now_showing', 'is_featured' => false,
                'synopsis' => 'A gripping tale of courage and survival as a community bands together against impossible odds.'],
            ['title' => '180', 'poster' => 'images/image 37.jpg', 'backdrop' => 'images/image 37.jpg', 'genre' => 'Drama', 'duration' => '2h 05m', 'rating' => 'PG-13', 'classification' => 'now_showing', 'is_featured' => false,
                'synopsis' => 'A moving story about second chances and the moments that turn a life completely around.'],
            ['title' => 'Inside the Wild', 'poster' => 'images/image 2.jpg', 'backdrop' => 'images/image 2.jpg', 'genre' => 'Adventure', 'duration' => '1h 58m', 'rating' => 'PG', 'classification' => 'coming_soon', 'is_featured' => false,
                'synopsis' => 'An unforgettable journey into the heart of nature, where every moment is a discovery.'],
            ['title' => 'Echoes of Tomorrow', 'poster' => 'images/image 30.png', 'backdrop' => 'images/image 30.png', 'genre' => 'Sci-Fi', 'duration' => '2h 10m', 'rating' => 'PG-13', 'classification' => 'coming_soon', 'is_featured' => false,
                'synopsis' => 'When the future sends a warning, one woman must decide how far she will go to change what is coming.'],
            ['title' => 'The Last Stand', 'poster' => 'images/image 32.png', 'backdrop' => 'images/image 32.png', 'genre' => 'Action', 'duration' => '2h 02m', 'rating' => 'R', 'classification' => 'coming_soon', 'is_featured' => false,
                'synopsis' => 'A retired sheriff faces his greatest test as danger rolls into a town that thought it was forgotten.'],
        ];

        foreach ($movies as $i => $m) {
            Movie::updateOrCreate(
                ['slug' => Str::slug($m['title'])],
                [
                    'title' => $m['title'],
                    'genre' => $m['genre'],
                    'duration' => $m['duration'],
                    'rating' => $m['rating'],
                    'synopsis' => $m['synopsis'],
                    'poster_image' => $m['poster'],
                    'backdrop_image' => $m['backdrop'],
                    'trailer_url' => 'https://www.youtube.com/results?search_query='.urlencode($m['title'].' trailer'),
                    'adult_price' => 5000,
                    'child_price' => 3000,
                    'classification' => $m['classification'],
                    'showtimes' => $times,
                    'is_featured' => $m['is_featured'],
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );
        }
    }
}
