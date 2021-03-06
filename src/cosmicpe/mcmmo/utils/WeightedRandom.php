<?php

declare(strict_types=1);

namespace cosmicpe\mcmmo\utils;

use Ds\Deque;
use Generator;
use pocketmine\utils\Random;

// Source: http://www.keithschwarz.com/interesting/code/?dir=alias-method
abstract class WeightedRandom{

	/** @var float[] */
	private $probabilities = [];

	/** @var int[] */
	private $aliases = [];

	/** @var Random */
	private $random;

	/** @var array */
	protected $indexes = [];

	final public function add($value, float $weight) : void{
		$this->probabilities[] = $weight;
		$this->indexes[] = $value;
	}

	final public function count() : int{
		return count($this->probabilities);
	}

	final public function setup(bool $fill_null = false) : void{
		if($fill_null){
			$this->add(null, 1.0 - (array_sum($this->probabilities) / $this->count()));
		}

		// Store the underlying generator.
		$this->random = new Random();

		$probabilities_c = $this->count();

		// Compute the average probability and cache it for later use.
		$average = 1.0 / $probabilities_c;

		$probabilities = $this->probabilities;

		// Create two stacks to act as worklists as we populate the tables.
		$small = new Deque();
		$small->allocate($probabilities_c);
		$large = new Deque();
		$large->allocate($probabilities_c);

		// Populate the stacks with the input probabilities.
		for($i = 0; $i < $probabilities_c; ++$i){
			/**
			 * If the probability is below the average probability, then we add
			 * it to the small list; otherwise we add it to the large list.
			 */
			if($probabilities[$i] >= $average){
				$large->push($i);
			}else{
				$small->push($i);
			}
		}

		/**
		 * As a note: in the mathematical specification of the algorithm, we
		 * will always exhaust the small list before the big list.  However,
		 * due to floating point inaccuracies, this is not necessarily true.
		 * Consequently, this inner loop (which tries to pair small and large
		 * elements) will have to check that both lists aren't empty.
		 */
		while(!$small->isEmpty() && !$large->isEmpty()){
			/* Get the index of the small and the large probabilities. */
			$less = $small->pop();
			$more = $large->pop();

			/**
			 * These probabilities have not yet been scaled up to be such that
			 * 1/n is given weight 1.0.  We do this here instead.
			 */
			$this->probabilities[$less] = $probabilities[$less] * $probabilities_c;
			$this->aliases[$less] = $more;

			/**
			 * Decrease the probability of the larger one by the appropriate
			 * amount.
			 */
			$probabilities[$more] = ($probabilities[$more] + $probabilities[$less]) - $average;

			/**
			 * If the new probability is less than the average, add it into the
			 * small list; otherwise add it to the large list.
			 */
			if($probabilities[$more] >= 1.0 / $probabilities_c){
				$large->push($more);
			}else{
				$small->push($more);
			}
		}

		/**
		 * At this point, everything is in one list, which means that the
		 * remaining probabilities should all be 1/n.  Based on this, set them
		 * appropriately.  Due to numerical issues, we can't be sure which
		 * stack will hold the entries, so we empty both.
		 */
		while(!$small->isEmpty()){
			$this->probabilities[$small->pop()] = 1.0;
		}
		while(!$large->isEmpty()){
			$this->probabilities[$large->pop()] = 1.0;
		}
	}

	/**
	 * @param int $count
	 * @return int[]|Generator
	 */
	final public function generateIndexes(int $count) : Generator{
		$probabilities_c = count($this->probabilities);
		while(--$count >= 0){
			$index = $this->random->nextBoundedInt($probabilities_c);
			yield $this->random->nextFloat() <= $this->probabilities[$index] ? $index : $this->aliases[$index];
		}
	}

	/**
	 * Returns $this->indexes[$this->generateIndex($count)]
	 * @param int $count
	 * @return Generator
	 */
	abstract public function generate(int $count) : Generator;
}