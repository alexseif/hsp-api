<?php

/*
 * The following content was designed & implemented under AlexSeif.com
 */
include('bookedapi.php');

/**
 * Description of Reservations
 *
 * @author Alex Seif <me@alexseif.com>
 */
class Reservations
{

  /**
   *
   * @var bookedAPIclient
   */
  private $apiClient;

  /**
   *
   * @var array
   */
  private $reservations;

  /**
   *
   * @var \DateTimeZone
   */
  private $timezone;

  /**
   *
   * @var \DateTime
   */
  private $now;

  /**
   *
   * @var string
   */
  private $floorTitle;

  function __construct()
  {
    global $reservations;

    $this->apiClient = new bookedapiclient(BOOKEDAPIUSER, BOOKEDAPIPASSWORD);
    $this->timezone = new \DateTimeZone(YOURTIMEZONE);
    $this->now = new \DateTime();
    $this->reservations = $reservations;
  }

  function fetchData()
  {

    $this->apiClient = new bookedapiclient($username, $password);
    $this->fetchResources();
    $this->fetchReservations();
  }

  function getApiClient()
  {
    return $this->apiClient;
  }

  function fetchReservations()
  {
    $getReservations = $this->apiClient->getReservation();
    foreach ($getReservations['reservations'] as $key => $reservation) {
      $res = $this->apiClient->getReservation($reservation['referenceNumber']);
      if (count($res['attachments']) > 0) {
        $reservation['image'] = $this->fixImageUrl($res['attachments'][0]['url']);
      }
      $startDate = new \DateTime($reservation['startDate']);
      $startDate->setTimezone($this->timezone);
      $endDate = new \DateTime($reservation['endDate']);
      $endDate->setTimezone($this->timezone);
      $reservation['start'] = $startDate->format(TIME_FORMAT);
      $reservation['end'] = $endDate->format(TIME_FORMAT);
      $reservation['startTimestamp'] = $startDate->getTimestamp();
      $reservation['endTimestamp'] = $endDate->getTimestamp();
      $reservations['reservations'][] = $reservation;
      $arraySearch = array_search($reservation['title'], $reservations['title']);
      if ((false === $arraySearch) || !count($reservations['title'])) {
        $reservations['title'][] = $reservation['title'];
      }
    }
    //Clean up before save
    $this->reservationsFile = fopen("data/reservations.json", "w") or die("Unable to open file!");
    fwrite($this->reservationsFile, json_encode($reservations));
    fclose($this->reservationsFile);
  }

  function getReservations()
  {
    $str = file_get_contents('data/reservations.json');
    $this->reservations = json_decode($str, true); // decode the JSON into an associative array
    $this->reservations = $this->reservations['reservations'];
    return $this->reservations;
  }

  function fetchResources()
  {
    $resources = $this->apiClient->getResource();
    foreach ($resources['resources'] as $key => $resource) {
      foreach ($resource['customAttributes'] as $customAttribute) {
        $resources['resources'][$key][$customAttribute['id']] = $customAttribute['value'];
      }
      $resources['resources'][$resource['resourceId']] = $resource;
      unset($resources['resources'][$key]);
    }
    //Clean up before save
    $resourcesFile = fopen("data/resources.json", "w") or die("Unable to open file!");
    fwrite($resourcesFile, json_encode($resources));
    fclose($resourcesFile);
  }

  function getResources()
  {
    $str = file_get_contents('data/resources.json');
    $resources = json_decode($str, true); // decode the JSON into an associative array
    $resources['resources'];
    return $resources;
  }

  function getTimezone()
  {
    return $this->timezone;
  }

  function getNow()
  {
    return $this->now;
  }

  function getFloorTitle()
  {
    return $this->floorTitle;
  }

  function setFloorTitle($floorTitle)
  {
    $this->floorTitle = $floorTitle;
  }

  function getReservation($referenceNumber)
  {
    return $this->apiClient->getReservation($referenceNumber);
  }

  function getCurrentReservationByRoom($roomName)
  {
    $currentReservations = null;
    if (is_array($this->reservations['reservations'])) {
      foreach ($this->reservations['reservations'] as $reservation) {
        if ($reservation['resourceName'] == $roomName) {
          $reservationStart = new \DateTime($reservation['startDate']);
          $reservationEnd = new \DateTime($reservation['endDate']);
          if (($this->now >= $reservationStart) ||
              ($this->now >= $reservationEnd)) {

            $reservation['startDate'] = new \DateTime($reservation['startDate']);
            $reservation['endDate'] = new \DateTime($reservation['endDate']);

            $currentReservations[] = $reservation;
          }
        }
      }
    }
    return $currentReservations;
  }

  function getCurrentReservations()
  {
    $currentReservations = null;
    $reservationByFloorNo = null;
    if (is_array($this->reservations['reservations'])) {
      foreach ($this->reservations['reservations'] as $reservation) {
        $reservationStart = new \DateTime($reservation['startDate']);
        $reservationEnd = new \DateTime($reservation['endDate']);
        $days = $reservationStart->diff($this->now);
        if (0 == $days->days) {
          if (
              ($this->now <= $reservationStart) ||
              ($this->now <= $reservationEnd)
          ) {

            $reservation['startDate'] = new \DateTime($reservation['startDate']);
            $reservation['startDate']->setTimezone($this->timezone);
            $reservation['endDate'] = new \DateTime($reservation['endDate']);
            $reservation['endDate']->setTimezone($this->timezone);

            $resource = $this->getApiClient()->getResource(intval($reservation['resourceId']));
            $arrowDirection = NULL;

            foreach ($resource['customAttributes'] as $customAttribute) {
              switch ($customAttribute['id']) {
                case 3:
                  $reservation['floorTitle'] = $customAttribute['value'];
                  break;
                case 5:
                  $reservation['floorNo'] = $customAttribute['value'];
                  break;
                case 22:
                  $reservation['arrowDirection'] = $customAttribute['value'];
                  break;
              }
            }
            $reservationByFloorNo[$reservation['floorNo']][] = $reservation;
//          $currentReservations[] = $reservation;
          }
        }
      }
      krsort($reservationByFloorNo);
      foreach ($reservationByFloorNo as $floorNo => $this->reservations) {
        foreach ($this->reservations as $reservation) {
          $currentReservations[] = $reservation;
        }
      }
    }
    return $currentReservations;
  }

  function search($search)
  {
    $reservations = [];
    $current = $this->getCurrentReservations();
    foreach ($current as $reservation) {
      if (strtolower($search) == strtolower($reservation['title']))
        $reservations[] = $reservation;
    }
    return $reservations;
  }

  function getCurrentReservationsByFloor($floor)
  {
    $currentReservations = null;
    $this->fetchReservations();
    if (is_array($this->reservations['reservations'])) {
      foreach ($this->reservations['reservations'] as $reservation) {
        $reservationStart = new \DateTime($reservation['startDate']);
        $reservationEnd = new \DateTime($reservation['endDate']);
        if (($this->now >= $reservationStart) &&
            ($this->now >= $reservationEnd)) {
          $resource = $this->getApiClient()->getResource(intval($reservation['resourceId']));
          $arrowDirection = NULL;
          $inFloor = FALSE;
          foreach ($resource['customAttributes'] as $customAttribute) {
            switch ($customAttribute['id']) {
              case 3:
                $tmpFloorTitle = $customAttribute['value'];
                break;
              case 5:
                if ($floor == $customAttribute['value']) {
                  $inFloor = TRUE;
                }
                break;
              case 9:
                if (FALSE !== strpos($customAttribute['value'], 'left-arrow')) {
                  $arrowDirection = 'left';
                }
                break;
              case 10:
                if (FALSE !== strpos($customAttribute['value'], 'right-arrow')) {
                  $arrowDirection = 'right';
                }
                break;
            }
          }
          if ($inFloor) {
            $this->setFloorTitle($tmpFloorTitle);
            $reservation['startDate'] = new \DateTime($reservation['startDate']);
            $reservation['startDate']->setTimezone($this->timezone);
            $reservation['endDate'] = new \DateTime($reservation['endDate']);
            $reservation['endDate']->setTimezone($this->timezone);
            $reservation['arrowDirection'] = $arrowDirection;
            $currentReservations[] = $reservation;
          }
        }
      }
    }
    return $currentReservations;
  }

  /**
   * TODO: move to utility class or functions file
   * @param type $parsed_url
   * @return type
   */
  function unparse_url($parsed_url)
  {
    $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
    $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
    $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
    $user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
    $pass = isset($parsed_url['pass']) ? ':' . $parsed_url['pass'] : '';
    $pass = ($user || $pass) ? "$pass@" : '';
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
    $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
    return "$scheme$user$pass$host$port$path$query$fragment";
  }

  /**
   * TODO: move to utility class
   * @param type $imageUrl
   * @return type
   */
  function fixImageUrl($imageUrl)
  {
    $parts = parse_url($imageUrl);
    //TODO: fetch from config
    $parts['host'] = 'dev1.fmgegypt.net';
    $parts['path'] = '/hsp/Web' . $parts['path'];
    return $this->unparse_url($parts);
  }

}
