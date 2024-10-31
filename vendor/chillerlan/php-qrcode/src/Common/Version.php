<?php
/**
 * Class Version
 *
 * @created      19.11.2020
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2020 smiley
 * @license      MIT
 */

namespace WPSecurityNinja\Plugin\chillerlan\QRCode\Common;

use WPSecurityNinja\Plugin\chillerlan\QRCode\QRCodeException;

/**
 * Version related tables and methods
 */
final class Version{

	/**
	 * Enable version auto detection
	 *
	 * @see \chillerlan\QRCode\QROptionsTrait::$version
	 *
	 * @var int
	 */
	public const AUTO = -1;

	/**
	 * ISO/IEC 18004:2000 Annex E, Table E.1 - Row/column coordinates of center module of Alignment Patterns
	 *
	 * version -> pattern
	 *
	 * @var int[][]
	 */
	private const ALIGNMENT_PATTERN = [
		1  => [],
		2  => [6, 18],
		3  => [6, 22],
		4  => [6, 26],
		5  => [6, 30],
		6  => [6, 34],
		7  => [6, 22, 38],
		8  => [6, 24, 42],
		9  => [6, 26, 46],
		10 => [6, 28, 50],
		11 => [6, 30, 54],
		12 => [6, 32, 58],
		13 => [6, 34, 62],
		14 => [6, 26, 46, 66],
		15 => [6, 26, 48, 70],
		16 => [6, 26, 50, 74],
		17 => [6, 30, 54, 78],
		18 => [6, 30, 56, 82],
		19 => [6, 30, 58, 86],
		20 => [6, 34, 62, 90],
		21 => [6, 28, 50, 72,  94],
		22 => [6, 26, 50, 74,  98],
		23 => [6, 30, 54, 78, 102],
		24 => [6, 28, 54, 80, 106],
		25 => [6, 32, 58, 84, 110],
		26 => [6, 30, 58, 86, 114],
		27 => [6, 34, 62, 90, 118],
		28 => [6, 26, 50, 74,  98, 122],
		29 => [6, 30, 54, 78, 102, 126],
		30 => [6, 26, 52, 78, 104, 130],
		31 => [6, 30, 56, 82, 108, 134],
		32 => [6, 34, 60, 86, 112, 138],
		33 => [6, 30, 58, 86, 114, 142],
		34 => [6, 34, 62, 90, 118, 146],
		35 => [6, 30, 54, 78, 102, 126, 150],
		36 => [6, 24, 50, 76, 102, 128, 154],
		37 => [6, 28, 54, 80, 106, 132, 158],
		38 => [6, 32, 58, 84, 110, 136, 162],
		39 => [6, 26, 54, 82, 110, 138, 166],
		40 => [6, 30, 58, 86, 114, 142, 170],
	];

	/**
	 * ISO/IEC 18004:2000 Annex D, Table D.1 - Version information bit stream for each version
	 *
	 * no version pattern for QR Codes < 7
	 *
	 * @var int[]
	 */
	private const VERSION_PATTERN = [
		7  => 0b000111110010010100,
		8  => 0b001000010110111100,
		9  => 0b001001101010011001,
		10 => 0b001010010011010011,
		11 => 0b001011101111110110,
		12 => 0b001100011101100010,
		13 => 0b001101100001000111,
		14 => 0b001110011000001101,
		15 => 0b001111100100101000,
		16 => 0b010000101101111000,
		17 => 0b010001010001011101,
		18 => 0b010010101000010111,
		19 => 0b010011010100110010,
		20 => 0b010100100110100110,
		21 => 0b010101011010000011,
		22 => 0b010110100011001001,
		23 => 0b010111011111101100,
		24 => 0b011000111011000100,
		25 => 0b011001000111100001,
		26 => 0b011010111110101011,
		27 => 0b011011000010001110,
		28 => 0b011100110000011010,
		29 => 0b011101001100111111,
		30 => 0b011110110101110101,
		31 => 0b011111001001010000,
		32 => 0b100000100111010101,
		33 => 0b100001011011110000,
		34 => 0b100010100010111010,
		35 => 0b100011011110011111,
		36 => 0b100100101100001011,
		37 => 0b100101010000101110,
		38 => 0b100110101001100100,
		39 => 0b100111010101000001,
		40 => 0b101000110001101001,
	];

	/**
	 * ISO/IEC 18004:2000 Tables 13-22 - Error correction characteristics
	 *
	 * @see http://www.thonky.com/qr-code-tutorial/error-correction-table
	 */
	private const RSBLOCKS = [
		1  => [[ 7, [[ 1,  19], [ 0,   0]]], [10, [[ 1, 16], [ 0,  0]]], [13, [[ 1, 13], [ 0,  0]]], [17, [[ 1,  9], [ 0,  0]]]],
		2  => [[10, [[ 1,  34], [ 0,   0]]], [16, [[ 1, 28], [ 0,  0]]], [22, [[ 1, 22], [ 0,  0]]], [28, [[ 1, 16], [ 0,  0]]]],
		3  => [[15, [[ 1,  55], [ 0,   0]]], [26, [[ 1, 44], [ 0,  0]]], [18, [[ 2, 17], [ 0,  0]]], [22, [[ 2, 13], [ 0,  0]]]],
		4  => [[20, [[ 1,  80], [ 0,   0]]], [18, [[ 2, 32], [ 0,  0]]], [26, [[ 2, 24], [ 0,  0]]], [16, [[ 4,  9], [ 0,  0]]]],
		5  => [[26, [[ 1, 108], [ 0,   0]]], [24, [[ 2, 43], [ 0,  0]]], [18, [[ 2, 15], [ 2, 16]]], [22, [[ 2, 11], [ 2, 12]]]],
		6  => [[18, [[ 2,  68], [ 0,   0]]], [16, [[ 4, 27], [ 0,  0]]], [24, [[ 4, 19], [ 0,  0]]], [28, [[ 4, 15], [ 0,  0]]]],
		7  => [[20, [[ 2,  78], [ 0,   0]]], [18, [[ 4, 31], [ 0,  0]]], [18, [[ 2, 14], [ 4, 15]]], [26, [[ 4, 13], [ 1, 14]]]],
		8  => [[24, [[ 2,  97], [ 0,   0]]], [22, [[ 2, 38], [ 2, 39]]], [22, [[ 4, 18], [ 2, 19]]], [26, [[ 4, 14], [ 2, 15]]]],
		9  => [[30, [[ 2, 116], [ 0,   0]]], [22, [[ 3, 36], [ 2, 37]]], [20, [[ 4, 16], [ 4, 17]]], [24, [[ 4, 12], [ 4, 13]]]],
		10 => [[18, [[ 2,  68], [ 2,  69]]], [26, [[ 4, 43], [ 1, 44]]], [24, [[ 6, 19], [ 2, 20]]], [28, [[ 6, 15], [ 2, 16]]]],
		11 => [[20, [[ 4,  81], [ 0,   0]]], [30, [[ 1, 50], [ 4, 51]]], [28, [[ 4, 22], [ 4, 23]]], [24, [[ 3, 12], [ 8, 13]]]],
		12 => [[24, [[ 2,  92], [ 2,  93]]], [22, [[ 6, 36], [ 2, 37]]], [26, [[ 4, 20], [ 6, 21]]], [28, [[ 7, 14], [ 4, 15]]]],
		13 => [[26, [[ 4, 107], [ 0,   0]]], [22, [[ 8, 37], [ 1, 38]]], [24, [[ 8, 20], [ 4, 21]]], [22, [[12, 11], [ 4, 12]]]],
		14 => [[30, [[ 3, 115], [ 1, 116]]], [24, [[ 4, 40], [ 5, 41]]], [20, [[11, 16], [ 5, 17]]], [24, [[11, 12], [ 5, 13]]]],
		15 => [[22, [[ 5,  87], [ 1,  88]]], [24, [[ 5, 41], [ 5, 42]]], [30, [[ 5, 24], [ 7, 25]]], [24, [[11, 12], [ 7, 13]]]],
		16 => [[24, [[ 5,  98], [ 1,  99]]], [28, [[ 7, 45], [ 3, 46]]], [24, [[15, 19], [ 2, 20]]], [30, [[ 3, 15], [13, 16]]]],
		17 => [[28, [[ 1, 107], [ 5, 108]]], [28, [[10, 46], [ 1, 47]]], [28, [[ 1, 22], [15, 23]]], [28, [[ 2, 14], [17, 15]]]],
		18 => [[30, [[ 5, 120], [ 1, 121]]], [26, [[ 9, 43], [ 4, 44]]], [28, [[17, 22], [ 1, 23]]], [28, [[ 2, 14], [19, 15]]]],
		19 => [[28, [[ 3, 113], [ 4, 114]]], [26, [[ 3, 44], [11, 45]]], [26, [[17, 21], [ 4, 22]]], [26, [[ 9, 13], [16, 14]]]],
		20 => [[28, [[ 3, 107], [ 5, 108]]], [26, [[ 3, 41], [13, 42]]], [30, [[15, 24], [ 5, 25]]], [28, [[15, 15], [10, 16]]]],
		21 => [[28, [[ 4, 116], [ 4, 117]]], [26, [[17, 42], [ 0,  0]]], [28, [[17, 22], [ 6, 23]]], [30, [[19, 16], [ 6, 17]]]],
		22 => [[28, [[ 2, 111], [ 7, 112]]], [28, [[17, 46], [ 0,  0]]], [30, [[ 7, 24], [16, 25]]], [24, [[34, 13], [ 0,  0]]]],
		23 => [[30, [[ 4, 121], [ 5, 122]]], [28, [[ 4, 47], [14, 48]]], [30, [[11, 24], [14, 25]]], [30, [[16, 15], [14, 16]]]],
		24 => [[30, [[ 6, 117], [ 4, 118]]], [28, [[ 6, 45], [14, 46]]], [30, [[11, 24], [16, 25]]], [30, [[30, 16], [ 2, 17]]]],
		25 => [[26, [[ 8, 106], [ 4, 107]]], [28, [[ 8, 47], [13, 48]]], [30, [[ 7, 24], [22, 25]]], [30, [[22, 15], [13, 16]]]],
		26 => [[28, [[10, 114], [ 2, 115]]], [28, [[19, 46], [ 4, 47]]], [28, [[28, 22], [ 6, 23]]], [30, [[33, 16], [ 4, 17]]]],
		27 => [[30, [[ 8, 122], [ 4, 123]]], [28, [[22, 45], [ 3, 46]]], [30, [[ 8, 23], [26, 24]]], [30, [[12, 15], [28, 16]]]],
		28 => [[30, [[ 3, 117], [10, 118]]], [28, [[ 3, 45], [23, 46]]], [30, [[ 4, 24], [31, 25]]], [30, [[11, 15], [31, 16]]]],
		29 => [[30, [[ 7, 116], [ 7, 117]]], [28, [[21, 45], [ 7, 46]]], [30, [[ 1, 23], [37, 24]]], [30, [[19, 15], [26, 16]]]],
		30 => [[30, [[ 5, 115], [10, 116]]], [28, [[19, 47], [10, 48]]], [30, [[15, 24], [25, 25]]], [30, [[23, 15], [25, 16]]]],
		31 => [[30, [[13, 115], [ 3, 116]]], [28, [[ 2, 46], [29, 47]]], [30, [[42, 24], [ 1, 25]]], [30, [[23, 15], [28, 16]]]],
		32 => [[30, [[17, 115], [ 0,   0]]], [28, [[10, 46], [23, 47]]], [30, [[10, 24], [35, 25]]], [30, [[19, 15], [35, 16]]]],
		33 => [[30, [[17, 115], [ 1, 116]]], [28, [[14, 46], [21, 47]]], [30, [[29, 24], [19, 25]]], [30, [[11, 15], [46, 16]]]],
		34 => [[30, [[13, 115], [ 6, 116]]], [28, [[14, 46], [23, 47]]], [30, [[44, 24], [ 7, 25]]], [30, [[59, 16], [ 1, 17]]]],
		35 => [[30, [[12, 121], [ 7, 122]]], [28, [[12, 47], [26, 48]]], [30, [[39, 24], [14, 25]]], [30, [[22, 15], [41, 16]]]],
		36 => [[30, [[ 6, 121], [14, 122]]], [28, [[ 6, 47], [34, 48]]], [30, [[46, 24], [10, 25]]], [30, [[ 2, 15], [64, 16]]]],
		37 => [[30, [[17, 122], [ 4, 123]]], [28, [[29, 46], [14, 47]]], [30, [[49, 24], [10, 25]]], [30, [[24, 15], [46, 16]]]],
		38 => [[30, [[ 4, 122], [18, 123]]], [28, [[13, 46], [32, 47]]], [30, [[48, 24], [14, 25]]], [30, [[42, 15], [32, 16]]]],
		39 => [[30, [[20, 117], [ 4, 118]]], [28, [[40, 47], [ 7, 48]]], [30, [[43, 24], [22, 25]]], [30, [[10, 15], [67, 16]]]],
		40 => [[30, [[19, 118], [ 6, 119]]], [28, [[18, 47], [31, 48]]], [30, [[34, 24], [34, 25]]], [30, [[20, 15], [61, 16]]]],
	];

	/**
	 * ISO/IEC 18004:2000 Table 1 - Data capacity of all versions of QR Code
	 */
	private const TOTAL_CODEWORDS = [
		1  => 26,
		2  => 44,
		3  => 70,
		4  => 100,
		5  => 134,
		6  => 172,
		7  => 196,
		8  => 242,
		9  => 292,
		10 => 346,
		11 => 404,
		12 => 466,
		13 => 532,
		14 => 581,
		15 => 655,
		16 => 733,
		17 => 815,
		18 => 901,
		19 => 991,
		20 => 1085,
		21 => 1156,
		22 => 1258,
		23 => 1364,
		24 => 1474,
		25 => 1588,
		26 => 1706,
		27 => 1828,
		28 => 1921,
		29 => 2051,
		30 => 2185,
		31 => 2323,
		32 => 2465,
		33 => 2611,
		34 => 2761,
		35 => 2876,
		36 => 3034,
		37 => 3196,
		38 => 3362,
		39 => 3532,
		40 => 3706,
	];

	/**
	 * QR Code version number
	 */
	private int $version;

	/**
	 * Version constructor.
	 *
	 * @throws \chillerlan\QRCode\QRCodeException
	 */
	public function __construct(int $version){

		if($version < 1 || $version > 40){
			throw new QRCodeException('invalid version given');
		}

		$this->version = $version;
	}

	/**
	 * returns the current version number as string
	 */
	public function __toString():string{
		return (string)$this->version;
	}

	/**
	 * returns the current version number
	 */
	public function getVersionNumber():int{
		return $this->version;
	}

	/**
	 * the matrix size for the given version
	 */
	public function getDimension():int{
		return (($this->version * 4) + 17);
	}

	/**
	 * the version pattern for the given version
	 */
	public function getVersionPattern():?int{
		return (self::VERSION_PATTERN[$this->version] ?? null);
	}

	/**
	 * the alignment patterns for the current version
	 *
	 * @return int[]
	 */
	public function getAlignmentPattern():array{
		return self::ALIGNMENT_PATTERN[$this->version];
	}

	/**
	 * returns ECC block information for the given $version and $eccLevel
	 */
	public function getRSBlocks(EccLevel $eccLevel):array{
		return self::RSBLOCKS[$this->version][$eccLevel->getOrdinal()];
	}

	/**
	 * returns the maximum codewords for the current version
	 */
	public function getTotalCodewords():int{
		return self::TOTAL_CODEWORDS[$this->version];
	}

}
