<?php

use Drupal\Core\Site\Settings;
use GuzzleHttp\Client;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\Core\File\FileSystemInterface;

// Get API key from Drupal settings.
$api_key = Settings::get('api_key');

$recipe_name = 'grilled cheeseburger';

$prompt = 'Create an image for a ' . $recipe_name . ' recipe';
$image_url = get_openai_image($prompt, $api_key);

// Save image to Drupal.
$data = file_get_contents($image_url);
$file = \Drupal::service('file.repository')->writeData($data, 'public://image.png', FileSystemInterface::EXISTS_REPLACE);
$media = Media::create([
    'bundle' => 'image',
    'name' => $recipe_name,
    'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $recipe_name,
        'title' => $recipe_name,
    ],
]);

$media->save();

$prompt = 'Create a recipe for a ' . $recipe_name . ' recipe in HTML format';
$recipe = get_openai_recipe($prompt, 500, $api_key);

$prompt = 'Create a cutesy blog title for a ' . $recipe_name . ' recipe';
$title = get_openai_recipe($prompt, 20, $api_key);

// Create a node article with recipe, title and image.
$node = Node::create([
    'type' => 'article',
    'title' => $title,
    'body' => [
        'value' => $recipe,
        'format' => 'full_html',
    ],
    'field_media_image' => [
        'target_id' => $media->id(),
    ],
]);

$node->save();

print $node->toUrl('canonical', ['absolute' => TRUE])->toString() . "\n";

/**
 * Get OpenAI Recipe
 *
 * @param string $prompt
 * @param integer $max_tokens
 * @param string $api_key
 * @return string
 */
function get_openai_recipe($prompt, $max_tokens, $api_key) {
    $url = 'https://api.openai.com/v1/completions';
    $temperature = 0.7;
    $model = 'text-davinci-003';

    $client = new Client();
    $response = $client->request('POST', $url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'prompt' => $prompt,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
            'model' =>  $model,
        ],
    ]);

    return json_decode($response->getBody()->getContents())->choices[0]->text;
}

/**
 * Get OpenAI Image
 *
 * @param string $prompt
 * @param string $api_key
 * @return string
 */
function get_openai_image($prompt, $api_key) {
    $url = 'https://api.openai.com/v1/images/generations';

    $client = new Client();
    $response = $client->request('POST', $url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'prompt' => $prompt,
            'n' => 1,
            'size' => '512x512',
        ],
    ]);

    return json_decode($response->getBody()->getContents())->data[0]->url;
}
