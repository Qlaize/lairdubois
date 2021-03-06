<?php

namespace Ladb\CoreBundle\Utils;

use CommerceGuys\Addressing\Address;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use Ladb\CoreBundle\Model\LocalisableExtendedInterface;
use Ladb\CoreBundle\Model\LocalisableInterface;

class LocalisableUtils extends AbstractContainerAwareUtils {

	const NAME = 'ladb_core.localisable_utils';

	/////

	public function geocodeLocation(LocalisableInterface $localisable) {
		if (!is_null($localisable->getLocation())) {

			$googleApiKey = $this->getParameter('google_api_key');
			$adapter  = new \Ivory\HttpAdapter\CurlHttpAdapter();
			$geocoder = new \Geocoder\Provider\GoogleMaps($adapter, null, null, true, $googleApiKey);
			$geocoder->setLocale('fr_FR');

			try {
				$response = $geocoder->geocode($localisable->getLocation());
			} catch (\Exception $e) {
				return false;
			}

			if ($response->count() > 0) {

				$address = $response->first();

				// Location
				$localisable->setLatitude($address->getLatitude());
				$localisable->setLongitude($address->getLongitude());

				if ($localisable instanceof LocalisableExtendedInterface) {

					// PostalCode /////

					$postalCode = $address->getPostalCode();
					if ($postalCode) {
						$localisable->setPostalCode($postalCode);
					}

					// Locality /////

					$locality = $address->getLocality();
					if ($locality) {
						$localisable->setLocality($locality);
					}

					// Country /////

					$country = $address->getCountry();
					if ($country) {
						$localisable->setCountry($country->getName());
					}

					// GeographicalAreas /////

					$geographicalAreaParts = array();

					if ($locality) {
						$geographicalAreaParts[] = $locality;
					}
					$adminLevels = $address->getAdminLevels();
					for ($i = $adminLevels->count(); $i > 0; $i--) {
						$adminLevel = $adminLevels->get($i);
						$geographicalAreaParts[] = $adminLevel->getName();
					}
					if ($country) {
						$geographicalAreaParts[] = $country->getName();
					}

					if (!empty($geographicalAreaParts)) {
						$localisable->setGeographicalAreas(implode(',', $geographicalAreaParts));
					} else {
						$localisable->setGeographicalAreas(null);
					}

					// FormattedAddress /////

					$addressFormatRepository = new AddressFormatRepository();
					$countryRepository = new CountryRepository();
					$subdivisionRepository = new SubdivisionRepository();
					$formatter = new DefaultFormatter($addressFormatRepository, $countryRepository, $subdivisionRepository, array( 'locale' => '\'fr_FR\'', 'html' => false ));

					$a = new Address();

					$a = $a->withCountryCode($address->getCountryCode());
					if ($address->getStreetNumber() && $address->getStreetName()) {
						$a = $a->withAddressLine1($address->getStreetNumber().' '.$address->getStreetName());
					} else if ($address->getStreetName()) {
						$a = $a->withAddressLine1($address->getStreetName());
					}
					if ($postalCode) {
						$a = $a->withPostalCode($postalCode);
					}
					if ($locality) {
						$a = $a->withLocality($locality);
					}
					if ($address->getSubLocality()) {
						$a = $a->withDependentLocality($address->getSubLocality());
					}
					if ($address->getAdminLevels()->first()) {
						$a = $a->withAdministrativeArea($address->getAdminLevels()->first()->getName());
					}

					$localisable->setFormattedAddress($formatter->format($a));

				}

				return true;
			}

		} else {
			$localisable->setLatitude(null);
			$localisable->setLongitude(null);
			if ($localisable instanceof LocalisableExtendedInterface) {
				$localisable->setGeographicalAreas(null);
				$localisable->setPostalCode(null);
				$localisable->setLocality(null);
				$localisable->setFormattedAddress($localisable->getLocation());
			}
		}

		return false;
	}

	/////

	public function getTopLeftBottomRightBounds($address) {

		$googleApiKey = $this->getParameter('google_api_key');
		try {

			$url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($address).'&key='.$googleApiKey.'&language=fr&region=FR';
			$contents = file_get_contents($url);
			$hash = json_decode($contents, true);

			if ($hash && isset($hash['results']) && is_array($hash['results'])) {

				foreach ($hash['results'] as $result) {

					if (isset($result['geometry']) && isset($result['geometry']['bounds'])) {

						$bounds = $result['geometry']['bounds'];

						// Returns an Elasticsearch ready bounds array [ top_left, bottom_right ]
						return array(
							$bounds['northeast']['lat'].','.$bounds['southwest']['lng'],
							$bounds['southwest']['lat'].','.$bounds['northeast']['lng'],
						);
					}

				}

			}

		} catch (\Exception $e) {
			$this->get('logger')->err($e);
		}

		return null;
	}

}