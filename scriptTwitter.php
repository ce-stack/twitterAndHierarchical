<?php
require_once __DIR__ . '/vendor/autoload.php'; // Include composer autoloader
use Abraham\TwitterOAuth\TwitterOAuth; // Import the TwitterOAuth library
use MongoDB\Client; // Import the MongoDB library
use Kreait\Firebase\Factory; // Import the Firebase library

// Twitter API credentials
$consumer_key = 'YOUR_CONSUMER_KEY';
$consumer_secret = 'YOUR_CONSUMER_SECRET';
$access_token = 'YOUR_ACCESS_TOKEN';
$access_token_secret = 'YOUR_ACCESS_TOKEN_SECRET';

// MongoDB credentials
$mongodb_uri = 'mongodb://localhost:27017';
$mongodb_database = 'twitter';
$mongodb_collection = 'tweets';

// Firebase credentials
$firebase_credentials = [
    'project_id' => 'YOUR_PROJECT_ID',
    'key_file' => 'path/to/your/firebase_credentials.json',
];

// Connect to Twitter API
$connection = new TwitterOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);

// Search term
$search_term = 'YOUR_SEARCH_TERM';

// Fetch top 10000 tweets
$params = [
    'q' => $search_term,
    'count' => 100,
    'result_type' => 'popular',
];
$top_tweets = $connection->get('search/tweets', $params);
$top_tweets_data = $top_tweets->statuses;
while ($top_tweets->search_metadata->next_results) {
    $params = [];
    parse_str(substr($top_tweets->search_metadata->next_results, 1), $params);
    $top_tweets = $connection->get('search/tweets', $params);
    $top_tweets_data = array_merge($top_tweets_data, $top_tweets->statuses);
}
// Fetch recent 10000 tweets
$params = [
    'q' => $search_term,
    'count' => 100,
    'result_type' => 'recent',
];
$recent_tweets = $connection->get('search/tweets', $params);
$recent_tweets_data = $recent_tweets->statuses;
while ($recent_tweets->search_metadata->next_results) {
    $params = [];
    parse_str(substr($recent_tweets->search_metadata->next_results, 1), $params);
    $recent_tweets = $connection->get('search/tweets', $params);
    $recent_tweets_data = array_merge($recent_tweets_data, $recent_tweets->statuses);
}

// Connect to MongoDB
$mongodb_client = new Client($mongodb_uri);
$mongodb_collection = $mongodb_client->selectCollection($mongodb_database, $mongodb_collection);

// Store tweet and user data in MongoDB
foreach ($top_tweets_data as $tweet_data) {
    $tweet = [
        'id' => $tweet_data->id_str,
        'text' => $tweet_data->text,
        'user_id' => $tweet_data->user->id_str,
        'created_at' => $tweet_data->created_at,
    ];
    $user = [
        'id' => $tweet_data->user->id_str,
        'name' => $tweet_data->user->name,
        'screen_name' => $tweet_data->user->screen_name,
        'followers_count' => $tweet_data->user->followers_count,
    ];
    $mongodb_collection->insertOne($tweet);
    $mongodb_client->selectCollection($mongodb_database, 'users')->updateOne(['id' => $user['id']], ['$set' => $user], ['upsert' => true]);
}
foreach ($recent_tweets_data as $tweet_data) {
    $tweet = [
        'id' => $tweet_data->id_str,
        'text' => $tweet_data->text,
        'user_id' => $tweet_data->user->id_str,
        'created_at' => $tweet_data->created_at,
    ];
    $user = [
        'id' => $tweet_data->user->id_str,
        'name' => $tweet_data->user->name,
        'screen_name' => $tweet_data->user->screen_name,
        'followers_count' => $tweet_data->user->followers_count,
    ];
    $mongodb_collection->insertOne($tweet);
    $mongodb_client->selectCollection($mongodb_database, 'users')->updateOne(['id' => $user['id']], ['$set' => $user], ['upsert' => true]);
}

// Get top 20 users by followers and store in Firebase
$top_users = $mongodb_client->selectCollection($mongodb_database, 'users')->find([], ['sort' => ['followers_count' => -1], 'limit' => 20]);
$firebase_factory = (new Factory)->withServiceAccount($firebase_credentials['key_file'])->create();
$firebase_database = $firebase_factory->getDatabase();
$firebase_users_ref = $firebase_database->getReference('users');
foreach ($top_users as $user_data) {
    $user = [
        'id' => $user_data->id,
        'name' => $user_data->name,
        'screen_name' => $user_data->screen_name,
        'followers_count' => $user_data->followers_count,
    ];
    $firebase_users_ref->push($user);
}

// Get top 20 tweets with highest retweets from top users and display report
$top_user_ids = array_map(function ($user_data) {
    return $user_data->id;
}, iterator_to_array($top_users));
$top_tweets = $mongodb_collection->find(['user_id' => ['$in' => $top_user_ids]], ['sort' => ['retweet_count' => -1], 'limit' => 20]);
foreach ($top_tweets as $tweet_data) {
    echo "Tweet: {$tweet_data->text}\n";
    echo "User: {$tweet_data->user_id}\n";
    echo "Retweets: {$tweet_data->retweet_count}\n";
    echo "----------------------------------------\n";
}