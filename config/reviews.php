<?php
return ['window_hours' => max(1, min(720, (int) env('REVIEW_WINDOW_HOURS', 48)))];
