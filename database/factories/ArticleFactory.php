<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Article> */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $slug = 'articulo-'.fake()->unique()->numberBetween(1000, 99999);

        return [
            'post_ref' => 'T'.fake()->unique()->numberBetween(100, 9999).'-es',
            'id_tema' => 'T00',
            'idioma' => 'es',
            'slug' => $slug,
            'titulo' => 'Cómo elegir una web corporativa sin errores',
            'descripcion' => 'Guía práctica para comparar propuestas de web corporativa y saber qué preguntar antes de contratar un proveedor.',
            'seo_title' => 'Cómo elegir una web corporativa sin errores',
            'meta_description' => 'Guía práctica para comparar propuestas de web corporativa y saber qué preguntar antes de contratar a un proveedor de confianza.',
            'categoria' => 'diseno-web',
            'tipo' => 'guia',
            'aporte_propio' => 'Lista de comprobación propia para comparar presupuestos.',
            'body_md' => "Texto de prueba.\n\n## Resumen\n\n- Uno\n- Dos\n\n## Fuentes\n\n1. Fuente.",
            'fecha_borrador' => '2026-11-05',
        ];
    }

    /** Con 2 fuentes válidas, listo para pasar las reglas. */
    public function conFuentes(): static
    {
        return $this->afterCreating(function (Article $a) {
            foreach ([1, 2] as $i) {
                ArticleSource::create([
                    'article_id' => $a->id, 'orden' => $i, 'titulo' => "Fuente $i", 'editorial' => 'Editorial',
                    'url' => "https://example.com/fuente-$i", 'fecha_consulta' => '2026-11-05',
                ]);
            }
        });
    }

    public function estado(string $estado): static
    {
        return $this->afterMaking(fn (Article $a) => $a->forceFill(['estado' => $estado]));
    }
}
