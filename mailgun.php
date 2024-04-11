<?php

if(!getenv('TEXT_TO_FIND') || !getenv('MAILGUN_API_KEY') || !getenv('MAILGUN_DOMAIN')) {
	echo 'Missing environment variables:' . PHP_EOL;

	if(!getenv('TEXT_TO_FIND')) {
		echo '* TEXT_TO_FIND' . PHP_EOL;
	}
	if(!getenv('MAILGUN_API_KEY')) {
		echo '* MAILGUN_API_KEY' . PHP_EOL;
	}
	if(!getenv('MAILGUN_DOMAIN')) {
		echo '* MAILGUN_DOMAIN' . PHP_EOL;
	}

	exit(22);
}

new MailgunVerification(getenv('TEXT_TO_FIND'), getenv('MAILGUN_API_KEY'), getenv('MAILGUN_DOMAIN'), !!getenv('MAILGUN_IS_EU'));

/**
 * Retrieve emails from Mailgun API and check if any email containing the testing text is delivered.
 */
class MailgunVerification
{
	/**
	 * Text to search for
	 *
	 * @var string
	 */
	protected $textToFindInEmail;

	/**
	 * API Key
	 *
	 * @var string
	 */
	protected $apiKey;

	/**
	 * The Mailgun API URL
	 * @var string
	 */
	protected $url;

	/**
	 * The message-id of the email found to contain the testing text
	 *
	 * @var string
	 */
	protected $testMessageId;

	/**
	 * MailgunVerification constructor
	 *
	 * @param string $textToFindInEmail The text to search for
	 * @param string $apiKey Mailgun API Key
	 * @param string $domain Mailgun Domain
	 * @param bool $isEu Is the domain using Mailgun EU?
	 */
	public function __construct($textToFindInEmail, $apiKey, $domain, $isEu = false)
	{
		// Set the parameters
		$this->apiKey = $apiKey;
		$this->textToFindInEmail = $textToFindInEmail;
		$this->url = 'https://api.' . ($isEu ? 'eu.' : '') . 'mailgun.net/v3/' . $domain . '/events';

		// Run the test
		$this->run();
	}

	/**
	 * Process the emails & search for testing text
	 */
	public function run()
	{
		// Try this three times
		for ($i = 1; $i <= 3; $i++) {
			// If this isn't our first rodeo
			if ($i > 1) {
				// Wait 5 seconds before tring again
				echo PHP_EOL . 'Waiting 5 seconds before trying again (attempt ' . $i . '/3)' . PHP_EOL;
				sleep(5);
			}

			// Process the emails
			$this->processEvents($this->getEmails());
		}

		// We didn't find a message that was sent, so there must have been some issue
		if ($this->testMessageId) {
			// We found a message, but it was never delivered
			echo '! Found the message but it was never delivered: ' . $this->testMessageId . PHP_EOL;
		} else {
			// We couldn't find a message with the testing text
			echo '! No messages found containing the text: ' . $this->textToFindInEmail . PHP_EOL;
		}

		// 61: No data available
		exit(61);
	}

	/**
	 * @param array $events
	 */
	protected function processEvents($events)
	{
		// How many emails to get through
		echo PHP_EOL . count($events) . ' emails found.' . PHP_EOL;

		foreach (($events ?? []) as $event) {
			// Output the ID
			// Note: The message-id is different to the email ID.
			echo '* Processing email ID: ' . $event->id . PHP_EOL;

			// We've yet to find the testing message
			if (!$this->testMessageId) {
				// Get the body of the email
				$body = $this->apiRequest($event->storage->url);

				// Check if the body contains the testing text
				$isTest = str_contains($body->{'stripped-text'}, $this->textToFindInEmail);

				// If we found the testing text
				if ($isTest) {
					echo '-> Email contains testing text. Status: ' . $event->event . PHP_EOL;

					// Set the message-id for future retrievals
					$this->testMessageId = $event->message->headers->{'message-id'};

					// Check event status
					$this->isDelivered($event);
				}
			}

			// If we know the message-id but need to check other events with the same ID
			if ($this->testMessageId && $this->testMessageId === $event->message->headers->{'message-id'}) {
				$this->isDelivered($event);
			}
		}
	}

	/**
	 * Get the emails from the Mailgun API
	 */
	protected function getEmails()
	{
		// Get the start date (either via env var or the current time minus an hour)
		$start = getenv('CI_PIPELINE_CREATED_AT') ?
			(new DateTime(getenv('CI_PIPELINE_CREATED_AT'))) : (new DateTime())->modify('-1 hours');

		// Get the emails restricted via message ID or dates
		$response = $this->apiRequest(
			$this->url,
			$this->testMessageId ?
				[
					'message-id' => '<' . $this->testMessageId . '>',
				] :
				[
					'begin' => (new DateTime())->format(DateTime::RFC2822),
					'end' => $start->format(DateTime::RFC2822),
				],

		);

		// Exit if we have no emails
		if (!$response->items) {
			echo '! No emails found.' . PHP_EOL;
			// 61: No data available
			exit(61);
		}

		return $response->items;
	}

	/**
	 * @param object $event
	 */
	protected function isDelivered($event)
	{
		if ($event->event === 'delivered') {
			echo '-> Mailgun delivered email ID: ' . $event->id . PHP_EOL;
			exit(0);
		}
	}


	/**
	 * Make the curl request correctly formatted
	 *
	 * @param string $url
	 * @param array $data
	 */
	public function apiRequest($url, $params = [])
	{
		$curl = curl_init();

		curl_setopt_array($curl, [
			CURLOPT_HTTPHEADER => [
				'Authorization: Basic ' . base64_encode('api:' . $this->apiKey)
			],
			CURLOPT_URL => $url . '?' . http_build_query($params),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => 'GET',
		]);


		$response = curl_exec($curl);
		$error = curl_error($curl);

		curl_close($curl);

		if ($error) {
			echo '! cURL Error #:' . curl_strerror($error);
			exit($error);
		}

		return json_decode($response);
	}
}
