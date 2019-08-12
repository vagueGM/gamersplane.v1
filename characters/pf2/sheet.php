			<div class="tr labelTR tr-noPadding">
				<label id="label_name" class="medText">Name</label>
				<label id="label_ancestry" class="medText">Ancestry & Heritage</label>
				<label id="label_background" class="medText">Background</label>
				<label id="label_charclass" class="medText">Class</label>
				<label id="label_level" class="shortText">Level</label>
				<label id="label_alignment" class="medText">Alignment</label>
			</div>
			<div class="tr dataTR">
				<div class="medText"><?=$this->getName()?></div>
				<div class="medText"><?=$this->getAncestry()?></div>
				<div class="medText"><?=$this->getBackground()?></div>
				<div class="medText"><? $this->getCharClass(); ?></div>
				<div class="shortNum"><? $this->getLevel(); ?></div>
				<div class="medText"><?=$this->getAlignment()?></div>
			</div>
