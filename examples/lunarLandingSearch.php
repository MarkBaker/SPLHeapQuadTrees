<?php

list(, $longitude, $latitude, $width, $height) = $argv + array(NULL, 0, 0, 40, 40);

include('../src/QuadTreeAutoloader.php');


//  Create a class for our data,
//      extending QuadTreeXYPoint so that we can use it for data points in our QuadTree
class lunarLandingPoint extends \QuadTrees\QuadTreeXYPoint
{
    public $name;
    public $launchDate;
    public $impactDate;

    public function __construct($name, $launchDate, $impactDate, $x, $y) {
        parent::__construct($x, $y);
        $this->name = $name;
        $this->launchDate = DateTime::createFromFormat('d M Y', $launchDate);
        $this->impactDate = DateTime::createFromFormat('d M Y', $impactDate);
    }
}


class LandingHeap extends \SPLHeap {

	protected $_longitude = 0;
	protected $_latitude = 0;

	protected function compare($a, $b) {
		return ($a->impactDate > $b->impactDate) ? -1 : 1;
    }

	public function setLongitude($longitude) {
		$this->_longitude = $longitude;
	}

	public function setLatitude($latitude) {
		$this->_latitude = $latitude;
	}

}


function buildQuadTree($filename) {
    //  Set the centrepoint of our QuadTree at 0.0 Longitude, 0.0 Latitude
    $centrePoint = new \QuadTrees\QuadTreeXYPoint(0.0, 0.0);
    //  Set the bounding box to the entire globe
    $quadTreeBoundingBox = new \QuadTrees\QuadTreeBoundingBox($centrePoint, 360, 180);
    //  Create our QuadTree
    $quadTree = new \QuadTrees\QuadTree($quadTreeBoundingBox);

    echo "Loading lunarLandings: ";
    $landingFile = new \SplFileObject($filename);
    $landingFile->setFlags(\SplFileObject::READ_CSV | \SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY);

    //  Populate our new QuadTree with lunarLandings from around the world
    $lunarLandingCount = 0;
    foreach($landingFile as $lunarLandingData) {
        if (!empty($lunarLandingData[0])) {
            if ($lunarLandingCount % 10 == 0) echo '.';
            $lunarLanding = new lunarLandingPoint(
                trim($lunarLandingData[0]),
                trim($lunarLandingData[1]),
                trim($lunarLandingData[2]),
                trim($lunarLandingData[4]),
                trim($lunarLandingData[3])
            );
            $quadTree->insert($lunarLanding);
            ++$lunarLandingCount;
        }
    }
    echo PHP_EOL, "Added $lunarLandingCount lunar landing details to QuadTree", PHP_EOL;
    return $quadTree;
}

/* Populate the quadtree  */
$startTime = microtime(true);

$lunarLandingsQuadTree = buildQuadTree(__DIR__ . "/../data/lunarLandings.csv");

$endTime = microtime(true);
$callTime = $endTime - $startTime;

echo 'Load Time: ', sprintf('%.4f',$callTime), ' s', PHP_EOL;
echo 'Current Memory: ', sprintf('%.2f',(memory_get_usage(false) / 1024 )), ' k', PHP_EOL;
echo 'Peak Memory: ', sprintf('%.2f',(memory_get_peak_usage(false) / 1024 )), ' k', PHP_EOL, PHP_EOL;


/* Search for lunarLandings within a bounding box */
$startTime = microtime(true);

//  Create a bounding box to search in, centred on the specified longitude and latitude
$searchCentrePoint = new \QuadTrees\QuadTreeXYPoint($longitude, $latitude);
//  Create the bounding box with specified dimensions
$searchBoundingBox = new \QuadTrees\QuadTreeBoundingBox($searchCentrePoint, $width, $height);

//  Search the cities QuadTree for all entries that fall within the defined bounding box
$searchResult = new LandingHeap();
$searchResult->setLongitude($searchBoundingBox->getCentrePoint()->getX());
$searchResult->setLatitude($searchBoundingBox->getCentrePoint()->getY());

$lunarLandingsQuadTree->search($searchBoundingBox, $searchResult);

//  Display the results
echo 'LunarLandings in range', PHP_EOL, 
    "    Latitude: ", sprintf('%+2f',$searchBoundingBox->getCentrePoint()->getY() - $searchBoundingBox->getHeight() / 2),
    ' -> ', sprintf('%+2f',$searchBoundingBox->getCentrePoint()->getY() + $searchBoundingBox->getHeight() / 2), PHP_EOL,
    "    Longitude: ", sprintf('%+2f',$searchBoundingBox->getCentrePoint()->getX() - $searchBoundingBox->getWidth() / 2),
    ' -> ', sprintf('%+2f',$searchBoundingBox->getCentrePoint()->getX() + $searchBoundingBox->getWidth() / 2), PHP_EOL, PHP_EOL;

if ($searchResult->count() == 0) {
    echo 'No matches found', PHP_EOL;
} else {
    foreach($searchResult as $result) {
        echo '    ', $result->name, ",\tLanded: ", 
            $result->impactDate->format('d M Y'), "  Lat: ", 
            sprintf('%+07.2f', $result->getY()), " Long: ", 
            sprintf('%+07.2f', $result->getX()), PHP_EOL;
    }
}
echo PHP_EOL;

$endTime = microtime(true);
$callTime = $endTime - $startTime;

echo 'Search Time: ', sprintf('%.4f',$callTime), ' s', PHP_EOL;
echo 'Current Memory: ', sprintf('%.2f',(memory_get_usage(false) / 1024 )), ' k', PHP_EOL;
echo 'Peak Memory: ', sprintf('%.2f',(memory_get_peak_usage(false) / 1024 )), ' k', PHP_EOL;
