import { combineReducers } from '@reduxjs/toolkit';
import auth from './auth';
import app from './app';

const reducers = combineReducers({
  auth,
  app,
});

export default reducers;
