<?php

list(, $longitude, $latitude, $width, $height) = $argv + array(NULL, -2.5, 55, 9, 10);

include('../src/QuadTreeAutoloader.php');


//  Create a class for our data,
//      extending QuadTreeXYPoint so that we can use it for data points in our QuadTree
class cityPoint extends \QuadTrees\QuadTreeXYPoint
{
    public $country;
    public $city;
    public $distance;

    public function __construct($country, $city, $x, $y) {
        parent::__construct($x, $y);
        $this->country = $country;
        $this->city = $city;
    }
}


class CitiesHeap extends \SPLHeap {

	protected $_longitude = 0;
	protected $_latitude = 0;

	protected function compare($a, $b) {
		if ($a->distance == $b->distance)
			return 0;
		return ($a->distance > $b->distance) ? -1 : 1;
    }

	public function setLongitude($longitude) {
		$this->_longitude = $longitude;
	}

	public function setLatitude($latitude) {
		$this->_latitude = $latitude;
	}

	protected function _calculateDistance($endPoint) { 
		$theta = $endPoint->getX() - $this->_longitude; 
		$distance = sin(deg2rad($endPoint->getY())) * sin(deg2rad($this->_latitude)) +  
			cos(deg2rad($endPoint->getY())) * cos(deg2rad($this->_latitude)) * 
			cos(deg2rad($theta)); 
		$distance = acos($distance); 
		$distance = rad2deg($distance); 
		$miles = $distance * 60 * 1.1515; 

		return $miles; 
	}

	public function insert($value) {
		$value->distance = 
            $this->_calculateDistance($value);
		parent::insert($value);
	}
}


function buildQuadTree($filename) {
    //  Set the centrepoint of our QuadTree at 0.0 Longitude, 0.0 Latitude
    $centrePoint = new \QuadTrees\QuadTreeXYPoint(0.0, 0.0);
    //  Set the bounding box to the entire globe
    $quadTreeBoundingBox = new \QuadTrees\QuadTreeBoundingBox($centrePoint, 360, 180);
    //  Create our QuadTree
    $quadTree = new \QuadTrees\QuadTree($quadTreeBoundingBox);

    echo "Loading cities: ";
    $cityFile = new \SplFileObject($filename);
    $cityFile->setFlags(\SplFileObject::READ_CSV | \SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY);

    //  Populate our new QuadTree with cities from around the world
    $cityCount = 0;
    foreach($cityFile as $cityData) {
        if (!empty($cityData[0])) {
            if ($cityCount % 1000 == 0) echo '.';
            $city = new cityPoint(
                $cityData[0],
                $cityData[1],
                $cityData[3],
                $cityData[2]
            );
            $quadTree->insert($city);
            ++$cityCount;
        }
    }
    echo PHP_EOL, "Added $cityCount cities to QuadTree", PHP_EOL;
    return $quadTree;
}

/* Populate the quadtree  */
$startTime = microtime(true);

$citiesQuadTree = buildQuadTree(__DIR__ . "/../data/citylist.csv");

$endTime = microtime(true);
$callTime = $endTime - $startTime;

echo 'Load Time: ', sprintf('%.4f',$callTime), ' s', PHP_EOL;
echo 'Current Memory: ', sprintf('%.2f',(memory_get_usage(false) / 1024 )), ' k', PHP_EOL;
echo 'Peak Memory: ', sprintf('%.2f',(memory_get_peak_usage(false) / 1024 )), ' k', PHP_EOL, PHP_EOL;


/* Search for cities within a bounding box */
$startTime = microtime(true);

//  Create a bounding box to search in, centred on the specified longitude and latitude
$searchCentrePoint = new \QuadTrees\QuadTreeXYPoint($longitude, $latitude);
//  Create the bounding box with specified dimensions
$searchBoundingBox = new \QuadTrees\QuadTreeBoundingBox($searchCentrePoint, $width, $height);

//  Search the cities QuadTree for all entries that fall within the defined bounding box
$searchResults = new CitiesHeap();
$searchResults->setLongitude($searchBoundingBox->getCentrePoint()->getX());
$searchResults->setLatitude($searchBoundingBox->getCentrePoint()->getY());

$citiesQuadTree->search($searchBoundingBox, $searchResults);

//  Display the results
echo 'Cities in range', PHP_EOL, 
    "    Latitude: ", sprintf('%+2f',$searchBoundingBox->getCentrePoint()->getY() - $searchBoundingBox->getHeight() / 2),
    ' -> ', sprintf('%+2f',$searchBoundingBox->getCentrePoint()->getY() + $searchBoundingBox->getHeight() / 2), PHP_EOL,
    "    Longitude: ", sprintf('%+2f',$searchBoundingBox->getCentrePoint()->getX() - $searchBoundingBox->getWidth() / 2),
    ' -> ', sprintf('%+2f',$searchBoundingBox->getCentrePoint()->getX() + $searchBoundingBox->getWidth() / 2), PHP_EOL, PHP_EOL;

if ($searchResults->count() == 0) {
    echo 'No matches found', PHP_EOL;
} else {
    foreach($searchResults as $result) {
        echo '    ', $result->city, ', ', 
            $result->country, ' => Lat: ', 
            sprintf('%+07.2f', $result->getY()), ' Long: ', 
            sprintf('%+07.2f', $result->getX()), ' | ',
            sprintf('%+07.2f', $result->distance), PHP_EOL;
    }
}
echo PHP_EOL;

$endTime = microtime(true);
$callTime = $endTime - $startTime;

echo 'Search Time: ', sprintf('%.4f',$callTime), ' s', PHP_EOL;
echo 'Current Memory: ', sprintf('%.2f',(memory_get_usage(false) / 1024 )), ' k', PHP_EOL;
echo 'Peak Memory: ', sprintf('%.2f',(memory_get_peak_usage(false) / 1024 )), ' k', PHP_EOL;
