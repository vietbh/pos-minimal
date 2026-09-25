import { startStimulusApp } from '@symfony/stimulus-bundle';
import ChangePasswordController from './controllers/change_password_controller.js';

const app = startStimulusApp();
app.register('change-password', ChangePasswordController);
