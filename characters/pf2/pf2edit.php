				<div class="tr labelTR">
					<label id="label_name" class="medText lrBuffer borderBox shiftRight">Name</label>
					<label id="label_race" class="medText lrBuffer borderBox shiftRight">Race</label>
					<label id="label_background" class="medText lrBuffer borderBox shiftRight">Background</label>
					<label id="label_charclass" class="medText lrBuffer borderBox shiftRight">Class</label>
					<label id="label_level" class="shortNum lrBuffer borderBox">Level</label>
				</div>
				<div class="tr">
					<input type="text" name="name" value="<?=$this->getName()?>" class="medText lrBuffer">
					<input type="text" name="race" value="<?=$this->getAncestry()?>" class="medText lrBuffer">
					<input type="text" name="background" value="<?=$this->getBackground()?>" class="medText lrBuffer">
					<input type="text" name="charclass" value="<?=$this->getCharClass()?>" class="medText lrBuffer">
					<input type="text" name="level" value="<?=$this->getLevel?>" class="shortNum lrBuffer">
				</div>