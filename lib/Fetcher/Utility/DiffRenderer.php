<?php

/**
 * @file
 *   Colorized Unified diff generator for PHP DiffLib.
 */

namespace Fetcher\Utility;

use Fetcher\SiteInterface,
    Diff_Renderer_Abstract,
    Diff_Renderer_Text_Unified,
    Colors\Color;

/**
 * This class is mostly a clone of Diff_Renderer_Text_Unified() from phpspec\php-diff with colorization.
 */
class DiffRenderer extends Diff_Renderer_Abstract {

  private $site = null;
  private $unified = null;

	/**
	 * The constructor. Instantiates the rendering engine and if options are passed,
	 * sets the options for the renderer.
	 *
	 * @param array $options Optionally, an array of the options for the renderer.
   * @param SiteInterface
	 */
	public function __construct(array $options = array(), $site) {
    $this->unified = new Diff_Renderer_Text_Unified($options);
		parent::setOptions($options);
    $this->site = $site;
	}

  /**
   * Implements the render() method to colorize output if appropriate.
   */
  public function render() {
    if ($this->site['system']->isTTY()) {
      return $this->renderColorText();
    }
    //return $this->unified->render();
  }

  /**
   * Renders colorized output.
   */
	private function renderColorText() {
    $c = new Color();
    $diff = '';
		$opCodes = $this->diff->getGroupedOpcodes();
		foreach ($opCodes as $group) {
			$lastItem = count($group) - 1;
			$i1 = $group[0][1];
			$i2 = $group[$lastItem][2];
			$j1 = $group[0][3];
			$j2 = $group[$lastItem][4];

			if($i1 == 0 && $i2 == 0) {
				$i1 = -1;
				$i2 = -1;
			}

			$diff .= '@@ -' . ($i1 + 1) . ',' . ($i2 - $i1) . ' +' . ($j1 + 1) . ',' . ($j2 - $j1) . " @@\n";
			foreach($group as $code) {
				list($tag, $i1, $i2, $j1, $j2) = $code;
				if($tag == 'equal') {
					$diff .= ' ' . implode("\n ", $this->diff->GetA($i1, $i2)) . "\n";
				}
				else {
					if($tag == 'replace' || $tag == 'delete') {
						$diff .= $c('-' . implode("\n-", $this->diff->GetA($i1, $i2)))->red . "\n";
					}

					if($tag == 'replace' || $tag == 'insert') {
						$diff .= $c('+' . implode("\n+", $this->diff->GetB($j1, $j2)))->green . "\n";
					}
				}
			}
		}
		return $diff;
  }

}
