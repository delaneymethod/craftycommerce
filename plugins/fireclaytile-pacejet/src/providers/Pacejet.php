<?php
/**
 * Fireclay Tile Pacejet plugin for Craft CMS 3.x
 *
 * Pacejet provider for Postie
 *
 * @link      https://solspace.com
 * @copyright Copyright (c) 2021 Solspace
 */

namespace solspace\fireclaytilepacejet\providers;

use Craft;
use Exception;
use Throwable;
use GuzzleHttp\Client;
use craft\helpers\Json;
use Twig\Error\SyntaxError;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use verbb\postie\base\Provider;
use craft\commerce\Plugin as Commerce;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class Pacejet
 *
 * @author     Solspace
 * @package    solspace\fireclaytilepacejet\providers
 * @since      1.0.0
 */
class Pacejet extends Provider {
	/**
	 * @var string
	 */
	public $weightUnit = 'g';

	/**
	 * @var string
	 */
	public $dimensionUnit = 'in';

	/**
	 * @return string
	 */
	public static function displayName(): string {
		return Craft::t('fireclaytile-pacejet', '{displayName}', [
			'displayName' => 'Pacejet',
		]);
	}

	/**
	 * @return string
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 * @throws Exception
	 */
	public function getSettingsHtml(): string {
		return Craft::$app->getView()->renderTemplate('fireclaytile-pacejet/provider/pacejet', [
			'provider' => $this
		]);
	}

	/**
	 * @return string
	 */
	public function getIconUrl(): string {
		return Craft::$app->assetManager->getPublishedUrl('@solspace/fireclaytilepacejet/resources/dist/img/pacejet.png', true);
	}

	/**
	 * @return string[]
	 */
	public function getServiceList(): array {
		return [
			'FIRECLAY_TILE_NATIONWIDE' => 'Fireclay Tile Nationwide',
		];
	}

	/**
	 * @param $order
	 * @return mixed
	 * @throws Exception
	 */
	public function fetchShippingRates($order) {
		// If we've locally cached the results, return that
		if ($this->_rates) {
			return $this->_rates;
		}

		$commerceInstance = Commerce::getInstance();

		$emailSenderAddress = $commerceInstance->getSettings()->emailSenderAddress;

		// Fetch the origin location - to be used to determine where to ship _from_
		$storeLocation = $commerceInstance->getAddresses()->getStoreLocationAddress();

		$origin = [];
		$origin['CompanyName'] = $storeLocation->businessName ?? '';
		$origin['Address1'] = $storeLocation->address1;
		$origin['Address2'] = $storeLocation->address2 ?? '';
		$origin['Address3'] = $storeLocation->address3 ?? '';
		$origin['City'] = $storeLocation->city;
		$origin['StateOrProvinceCode'] = $storeLocation->state->abbreviation;
		$origin['PostalCode'] = $storeLocation->zipCode;
		$origin['CountryCode'] = $storeLocation->countryIso;
		$origin['ContactName'] = $storeLocation->fullName;
		$origin['Email'] = $emailSenderAddress ?? '';
		$origin['Phone'] = $storeLocation->phone ?? '';

		$destination = [];
		$destination['CompanyName'] = $order->shippingAddress->businessName ?? '';
		$destination['Address1'] = $order->shippingAddress->address1;
		$destination['Address2'] = $order->shippingAddress->address2 ?? '';
		$destination['Address3'] = $order->shippingAddress->address3 ?? '';
		$destination['City'] = $order->shippingAddress->city;
		$destination['StateOrProvinceCode'] = $order->shippingAddress->state->abbreviation;
		$destination['PostalCode'] = $order->shippingAddress->zipCode;
		$destination['CountryCode'] = $order->shippingAddress->countryIso;
		$destination['ContactName'] = $order->shippingAddress->fullName;
		$destination['Email'] = $order->user->email; // TODO - Confirm this is the correct property value to use
		$destination['Phone'] = $order->shippingAddress->phone;

		// Pack the content of the order into boxes
		$packedBoxes = $this->packOrder($order)->getSerializedPackedBoxList();

		$packageDetailsList = array_map(function($packedBox) {
			return [
				'Weight' => $packedBox['weight'],
				'Dimensions' => [
					'Length' => $packedBox['length'],
					'Width' => $packedBox['width'],
					'Height' => $packedBox['height'],
					'Units' => $this->dimensionUnit, // It's critical that this matches Pacejet expectations. It must be IN for inches.
				],
			];
		}, $packedBoxes);

		// Allow location and dimensions modification via events
		$this->beforeFetchRates($storeLocation, $packedBoxes, $order);

		try {
			$payload = [
				'Origin' => $origin,
				'Destination' => $destination,
				'PackageDetailsList' => $packageDetailsList,
				'Location' => $this->getSetting('username'),
				'LicenseID' => $this->getSetting('licenseId'),
				'UpsLicenseID' => $this->getSetting('upsLicenseId'),
			];

			// Not entirely sure we need this but adding it in anyways as other providers seem to use it
			$this->beforeSendPayload($this,$payload, $order);

			$rates = $this->_getRequest('POST', 'Rates', [
				'body' => json_encode($payload),
			]);

			// Craft::dd($rates);
			// API RESPONSE

			// FIXME - Loop over $rates from API result
			foreach($this->getServiceList() as $key => $value) {
				$this->_rates[$key] = [
					'amount' => 10
				];
			}

			/*
			[
				'FIRECLAY_TILE_NATIONWIDE' => [
					'amount' => 10
				]
			]
			*/
		} catch (Throwable $error) {
			$this->_throwError($error);
		}

		// Craft::dd($this->_rates);
		return $this->_rates;
	}

	/**
	 * Allows user to manually test connection to API
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function fetchConnection(): bool {
		try {
			$payload = [
				'Origin' => [
					'CompanyName' => 'ShipItFaster.com',
					'Address1' => 'One Infinite Loop',
					'City' => 'Cupertino',
					'StateOrProvinceCode' => 'CA',
					'PostalCode' => '95014',
					'CountryCode' => 'US',
					'ContactName' => 'Steve Sellers',
					'Email' => 'steve.sellers@shipitfaster.com',
					'Phone' => '877-722-3538',
				],
				'Destination' => [
					'CompanyName' => 'Buysalot, Inc',
					'Address1' => '1600 Amphitheatre Parkway',
					'City' => 'Mountain View',
					'StateOrProvinceCode' => 'CA',
					'PostalCode' => '94043',
					'CountryCode' => 'US',
					'ContactName' => 'Bob Buyers',
					'Email' => 'bob.buyers@buysalot.com',
					'Phone' => '877-722-3538',
				],
				'PackageDetailsList' => [
					[
						'Weight' => '18',
						'Dimensions' => [
							'Length' => '10',
							'Width' => '12',
							'Height' => '18',
							'Units' => 'in',
						],
					],
				],
				'Location' => $this->getSetting('username'),
				'LicenseID' => $this->getSetting('licenseId'),
				'UpsLicenseID' => $this->getSetting('upsLicenseId'),
			];

			$this->_getRequest('POST', 'Rates', [
				'body' => json_encode($payload),
			]);
		} catch (Throwable $error) {
			$this->_throwError($error);

			return false;
		}

		return true;
	}

	/**
	 * @param string $method
	 * @param string $uri
	 * @param array $options
	 * @return mixed|null
	 * @throws GuzzleException
	 */
	private function _getRequest(string $method, string $uri, array $options = []) {
		$response = $this->_getClient()->request($method, ltrim($uri, '/'), $options);

		return Json::decode((string) $response->getBody());
	}

	/**
	 * @return Client
	 */
	private function _getClient(): Client {
		if ($this->_client) {
			return $this->_client;
		}

		return $this->_client = Craft::createGuzzleClient([
			'base_uri' => $this->getSetting('apiUrl'),
			'headers' => [
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
				'PacejetLocation' => $this->getSetting('username'),
				'PacejetLicenseKey' => $this->getSetting('licenseKey'),
			],
		]);
	}

	/**
	 * @param $error
	 * @throws Exception
	 */
	private function _throwError($error) {
		if (method_exists($error, 'hasResponse')) {
			$data = Json::decode((string) $error->getResponse()->getBody());

			$message = $data['error']['errorMessage'] ?? $error->getMessage();

			Provider::error($this, Craft::t('fireclaytile-pacejet', 'API error: "{message}" {file}:{line}', [
				'message' => $message,
				'file' => $error->getFile(),
				'line' => $error->getLine(),
			]));
		} else {
			Provider::error($this, Craft::t('fireclaytile-pacejet', 'API error: "{message}" {file}:{line}', [
				'file' => $error->getFile(),
				'line' => $error->getLine(),
				'message' => $error->getMessage(),
			]));
		}
	}
}
