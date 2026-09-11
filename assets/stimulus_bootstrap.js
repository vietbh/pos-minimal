import { startStimulusApp } from '@symfony/stimulus-bundle';
import StatisticsFilterController from './controllers/statistics_filter_controller.js';

const app = startStimulusApp();
app.register('statistics-filter', StatisticsFilterController);
