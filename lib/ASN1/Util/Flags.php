<?php

namespace ASN1\Util;

use ASN1\Type\Primitive\BitString;


class Flags
{
	/**
	 * Flag octets
	 *
	 * @var string $_flags
	 */
	protected $_flags;
	
	/**
	 * Number of flags
	 *
	 * @var string $_width
	 */
	protected $_width;
	
	/**
	 * Constructor
	 *
	 * @param number $flags Flags
	 * @param int $width Number of Flags. If width is larger than number of
	 *        bits in $flags, zeroes are prepended to flag field.
	 */
	public function __construct($flags, $width) {
		if (!$width) {
			$this->_flags = "";
		} else {
			// calculate number of unused bits in last octet
			$last_octet_bits = $width % 8;
			$unused_bits = $last_octet_bits ? 8 - $last_octet_bits : 0;
			$num = gmp_init($flags);
			// mask bits outside bitfield width
			$mask = gmp_sub(gmp_init(1) << $width, 1);
			$num &= $mask;
			// shift towards MSB if needed
			$data = gmp_export($num << $unused_bits, 1, 
				GMP_MSW_FIRST | GMP_BIG_ENDIAN);
			$octets = unpack("C*", $data);
			$bits = count($octets) * 8;
			// pad with zeroes
			while ($bits < $width) {
				array_unshift($octets, 0);
				$bits += 8;
			}
			$this->_flags = pack("C*", ...$octets);
		}
		$this->_width = $width;
	}
	
	/**
	 * Initialize from BitString
	 *
	 * @param BitString $bs
	 * @param int $width
	 * @return self
	 */
	public static function fromBitString(BitString $bs, $width) {
		$str = $bs->str();
		$num_bits = $bs->numBits();
		$num = gmp_import($str, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
		$num >>= $bs->unusedBits();
		if ($num_bits < $width) {
			$num <<= ($width - $num_bits);
		}
		return new self(gmp_strval($num, 10), $width);
	}
	
	/**
	 * Test whether bit at given index is set.
	 * Index 0 is the leftmost bit.
	 *
	 * @param int $idx
	 * @throws \OutOfBoundsException
	 * @return bool
	 */
	public function test($idx) {
		if ($idx >= $this->_width) {
			throw new \OutOfBoundsException("Index is out of bounds.");
		}
		// octet index
		$oi = (int) floor($idx / 8);
		$byte = $this->_flags[$oi];
		// bit index
		$bi = $idx % 8;
		// index 0 is the most significant bit in byte
		$mask = 0x01 << (7 - $bi);
		return (ord($byte) & $mask) > 0;
	}
	
	/**
	 * Get flags as an octet string.
	 * Zeroes are appended to the last octet
	 * if width is not divisible by 8.
	 *
	 * @return string
	 */
	public function str() {
		return $this->_flags;
	}
	
	/**
	 * Get flags as a base 10 integer
	 *
	 * @return number
	 */
	public function number() {
		$num = gmp_import($this->_flags, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
		$last_octet_bits = $this->_width % 8;
		$unused_bits = $last_octet_bits ? 8 - $last_octet_bits : 0;
		$num >>= $unused_bits;
		return gmp_strval($num, 10);
	}
	
	/**
	 * Get flags as a BitString.
	 * Unused bits are set accordingly.
	 * Trailing zeroes are not stripped.
	 *
	 * @return BitString
	 */
	public function bitString() {
		$last_octet_bits = $this->_width % 8;
		$unused_bits = $last_octet_bits ? 8 - $last_octet_bits : 0;
		return new BitString($this->_flags, $unused_bits);
	}
}